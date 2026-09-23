<?php
namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeLeave;
use App\Models\Holiday;
use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class EmployeeLeaveController extends Controller
{
    const JATAH_TAHUNAN = 12;

    // Cuti tahunan khusus karyawan Head Office — jatah 12 hari kerja/tahun, reset tiap 1 Januari.
    public function index(Request $request)
    {
        $tahun = (int) $request->get('tahun', now()->year);
        $hoProjectId = Project::where('kode', 'ho')->value('id');

        $employees = Employee::aktif()->where('project_id', $hoProjectId)
            ->with('position')->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'position_id'])
            ->map(fn ($e) => ['id' => $e->id, 'nama_lengkap' => $e->nama_lengkap, 'jabatan' => $e->position?->nama_jabatan ?? '-']);

        $leaves = EmployeeLeave::whereYear('tanggal_mulai', $tahun)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->orderByDesc('tanggal_mulai')
            ->get()
            ->map(fn ($l) => [
                'id'              => $l->id,
                'employee_id'     => $l->employee_id,
                'jenis'           => $l->jenis,
                'tanggal_mulai'   => $l->tanggal_mulai->format('Y-m-d'),
                'tanggal_selesai' => $l->tanggal_selesai->format('Y-m-d'),
                'jumlah_hari'     => $l->jumlah_hari,
                'keterangan'      => $l->keterangan,
                'dicatat_oleh'    => $l->dicatat_oleh,
            ]);

        $holidays = Holiday::whereYear('tanggal', $tahun)->get(['id', 'tanggal', 'keterangan', 'tipe'])
            ->map(fn ($h) => ['id' => $h->id, 'tanggal' => $h->tanggal->format('Y-m-d'), 'keterangan' => $h->keterangan, 'tipe' => $h->tipe]);

        return Inertia::render('Cuti/Index', [
            'employees' => $employees,
            'leaves'    => $leaves,
            'holidays'  => $holidays,
            'tahun'     => $tahun,
            'jatah'     => self::JATAH_TAHUNAN,
        ]);
    }

    // Export Excel gabungan Cuti Tahunan + Kehadiran — 3 sheet: rekap cuti, detail cuti,
    // dan ringkasan kehadiran semester (rumus sama persis dengan EmployeeAttendanceController::semester()).
    public function export(Request $request)
    {
        $tahun     = (int) $request->get('tahun', now()->year);
        $semester  = (int) $request->get('semester', now()->month <= 6 ? 1 : 2);
        $bulanAwal = $semester === 1 ? 1 : 7;
        $bulanAkhir = $semester === 1 ? 6 : 12;

        $hoProjectId = Project::where('kode', 'ho')->value('id');
        $employees = Employee::aktif()->where('project_id', $hoProjectId)
            ->with('position')->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'position_id']);

        $leaves = EmployeeLeave::whereYear('tanggal_mulai', $tahun)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->orderBy('employee_id')->orderByDesc('tanggal_mulai')
            ->get();
        $leavesByEmployee = $leaves->groupBy('employee_id');

        $attendanceRows = EmployeeAttendance::where('tahun', $tahun)
            ->whereBetween('bulan', [$bulanAwal, $bulanAkhir])
            ->whereIn('employee_id', $employees->pluck('id'))
            ->get()->groupBy('employee_id');

        $wb = new Spreadsheet();

        // ── SHEET 1: REKAP CUTI ──
        $sheet1 = $wb->getActiveSheet()->setTitle('Rekap Cuti');
        $this->exportTitle($sheet1, "REKAP CUTI TAHUNAN {$tahun} — PT. ANDALAS KARYA MULIA (HEAD OFFICE)", 'E', $employees->count());
        $headers1 = ['A' => ['No.', 4], 'B' => ['Nama Karyawan', 28], 'C' => ['Jabatan', 26], 'D' => ['Terpakai (hari)', 16], 'E' => ['Sisa (hari)', 14]];
        $this->exportHeaderRow($sheet1, $headers1, 4);
        foreach ($employees as $idx => $e) {
            $row = 5 + $idx;
            $terpakai = $leavesByEmployee->get($e->id, collect())->where('jenis', 'cuti_tahunan')->sum('jumlah_hari');
            $sisa = self::JATAH_TAHUNAN - $terpakai;
            $this->exportRow($sheet1, $row, $idx, [
                'A' => $idx + 1, 'B' => strtoupper($e->nama_lengkap), 'C' => $e->position?->nama_jabatan ?? '—',
                'D' => $terpakai, 'E' => $sisa,
            ], ['A', 'D', 'E']);
        }
        $sheet1->setAutoFilter("A4:E4");
        $sheet1->freezePane('B5');
        $sheet1->setShowGridlines(false);

        // ── SHEET 2: DETAIL CUTI ──
        $sheet2 = $wb->createSheet()->setTitle('Detail Cuti');
        $this->exportTitle($sheet2, "DETAIL CUTI TAHUNAN {$tahun} — PT. ANDALAS KARYA MULIA (HEAD OFFICE)", 'G', $leaves->count());
        $headers2 = [
            'A' => ['No.', 4], 'B' => ['Nama Karyawan', 28], 'C' => ['Jabatan', 24],
            'D' => ['Jenis', 20], 'E' => ['Tgl Mulai', 14], 'F' => ['Tgl Selesai', 14], 'G' => ['Jumlah Hari', 12],
            'H' => ['Keterangan', 28],
        ];
        $this->exportHeaderRow($sheet2, $headers2, 4);
        $employeesById = $employees->keyBy('id');
        foreach ($leaves as $idx => $l) {
            $row = 5 + $idx;
            $emp = $employeesById->get($l->employee_id);
            $this->exportRow($sheet2, $row, $idx, [
                'A' => $idx + 1, 'B' => strtoupper($emp?->nama_lengkap ?? '—'), 'C' => $emp?->position?->nama_jabatan ?? '—',
                'D' => $l->jenis === 'izin_tanpa_potong' ? 'Izin (Tanpa Potong Cuti)' : 'Cuti Tahunan',
                'E' => $l->tanggal_mulai->format('d M Y'), 'F' => $l->tanggal_selesai->format('d M Y'),
                'G' => $l->jumlah_hari, 'H' => $l->keterangan ?? '—',
            ], ['A', 'D', 'E', 'F', 'G']);
        }
        if ($leaves->isEmpty()) {
            $sheet2->mergeCells('A5:H5');
            $sheet2->setCellValue('A5', 'Belum ada catatan cuti.');
            $sheet2->getStyle('A5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        $sheet2->setAutoFilter('A4:H4');
        $sheet2->freezePane('B5');
        $sheet2->setShowGridlines(false);

        // ── SHEET 3: RINGKASAN KEHADIRAN ──
        $sheet3 = $wb->createSheet()->setTitle('Kehadiran');
        $periodeLabel = $semester === 1 ? 'Jan–Jun' : 'Jul–Des';
        $this->exportTitle($sheet3, "RINGKASAN KEHADIRAN SEMESTER {$semester} ({$periodeLabel}) {$tahun} — PT. ANDALAS KARYA MULIA (HEAD OFFICE)", 'H', $employees->count());
        $headers3 = [
            'A' => ['No.', 4], 'B' => ['Nama Karyawan', 28], 'C' => ['Jabatan', 24],
            'D' => ['Hadir', 10], 'E' => ['Izin', 10], 'F' => ['Sakit', 10], 'G' => ['Alpha', 10],
            'H' => ['% Kehadiran', 14],
        ];
        $this->exportHeaderRow($sheet3, $headers3, 4);
        foreach ($employees as $idx => $e) {
            $row = 5 + $idx;
            $recs  = $attendanceRows->get($e->id, collect());
            $hadir = $recs->sum('hadir'); $izin = $recs->sum('izin'); $sakit = $recs->sum('sakit'); $alpha = $recs->sum('alpha');
            $dasar = $hadir + $izin + $sakit + $alpha;
            $pct   = $dasar > 0 ? round($hadir / $dasar * 100, 1) . '%' : '—';
            $this->exportRow($sheet3, $row, $idx, [
                'A' => $idx + 1, 'B' => strtoupper($e->nama_lengkap), 'C' => $e->position?->nama_jabatan ?? '—',
                'D' => $hadir, 'E' => $izin, 'F' => $sakit, 'G' => $alpha, 'H' => $pct,
            ], ['A', 'D', 'E', 'F', 'G', 'H']);
        }
        $sheet3->setAutoFilter('A4:H4');
        $sheet3->freezePane('B5');
        $sheet3->setShowGridlines(false);

        $wb->setActiveSheetIndex(0);

        $writer = new Xlsx($wb);
        return response()->stream(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="Cuti_Kehadiran_' . now()->format('Ymd_His') . '.xlsx"',
            'Cache-Control'       => 'max-age=0',
        ]);
    }

    // ── Helper kecil khusus export Cuti & Kehadiran (judul, header, baris) ──
    private function exportTitle($sheet, string $title, string $lastCol, int $count): void
    {
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A1', $title);
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['name' => 'Arial', 'size' => 12, 'bold' => true, 'color' => ['rgb' => 'E8A020']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1A1A2E']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->setCellValue('A2', 'Diekspor pada: ' . now()->format('d M Y H:i') . ' WIB  |  Total: ' . $count . ' data');
        $sheet->getStyle('A2')->applyFromArray([
            'font'      => ['name' => 'Arial', 'size' => 9, 'italic' => true, 'color' => ['rgb' => '666666']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(16);
        $sheet->getRowDimension(3)->setRowHeight(6);
    }

    private function exportHeaderRow($sheet, array $headers, int $hRow): void
    {
        foreach ($headers as $col => [$label, $width]) {
            $sheet->setCellValue($col . $hRow, $label);
            $sheet->getStyle($col . $hRow)->applyFromArray([
                'font'      => ['name' => 'Arial', 'size' => 9, 'bold' => true],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9D9D9']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'AAAAAA']]],
            ]);
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        $sheet->getRowDimension($hRow)->setRowHeight(26);
    }

    private function exportRow($sheet, int $row, int $idx, array $values, array $centerCols = []): void
    {
        $rowBg = $idx % 2 === 0 ? 'FFFFFF' : 'F0F2F5';
        foreach ($values as $col => $val) {
            $sheet->setCellValue($col . $row, $val);
            $sheet->getStyle($col . $row)->applyFromArray([
                'font'      => ['name' => 'Arial', 'size' => 9],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rowBg]],
                'alignment' => ['horizontal' => in_array($col, $centerCols) ? Alignment::HORIZONTAL_CENTER : Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D0D3DC']]],
            ]);
        }
        $sheet->getRowDimension($row)->setRowHeight(15);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id'     => 'required|exists:employees,id',
            'jenis'           => 'nullable|in:cuti_tahunan,izin_tanpa_potong',
            'tanggal_mulai'   => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'keterangan'      => 'nullable|string|max:200',
        ]);
        $data['jenis'] = $data['jenis'] ?? 'cuti_tahunan';

        $jumlahHari = EmployeeLeave::hitungHariKerja($data['tanggal_mulai'], $data['tanggal_selesai']);
        if ($jumlahHari < 1) {
            return back()->withErrors(['tanggal_selesai' => 'Rentang tanggal ini tidak mengandung hari kerja (semua akhir pekan/hari libur).']);
        }

        $leave = EmployeeLeave::create([
            ...$data,
            'jumlah_hari'  => $jumlahHari,
            'dicatat_oleh' => auth()->user()?->name,
        ]);

        $labelJenis = $data['jenis'] === 'izin_tanpa_potong' ? 'Izin (tanpa potong cuti)' : 'Cuti';
        ActivityLog::record('create', 'Cuti', $leave->employee->nama_lengkap ?? '-', "Catat {$labelJenis} {$jumlahHari} hari kerja ({$data['tanggal_mulai']} s/d {$data['tanggal_selesai']})");

        return back()->with('success', "{$labelJenis} {$jumlahHari} hari kerja berhasil dicatat.");
    }

    public function destroy(EmployeeLeave $leave)
    {
        $nama = $leave->employee->nama_lengkap ?? '-';
        $leave->delete();
        ActivityLog::record('delete', 'Cuti', $nama, 'Catatan cuti dihapus');
        return back()->with('success', 'Catatan cuti berhasil dihapus.');
    }
}
