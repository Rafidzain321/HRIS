<?php
// app/Http/Controllers/EmployeeKpiController.php
namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\EmployeeGoal;
use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class EmployeeKpiController extends Controller
{
    // HR (permission edit-kpi) atau super-admin boleh kelola goal siapa saja. Selain itu, akun
    // yang terhubung ke data karyawan sendiri (users.employee_id) cuma boleh kelola goal dirinya
    // sendiri DAN bawahan langsungnya (atasan_id) — dipakai fitur self-input KPI per manajer
    // beserta dashboard tim mereka.
    private function canEditKpiFor(int $employeeId): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        if ($user->hasRole('super-admin') || $user->can('edit-kpi')) return true;
        if (!$user->employee_id) return false;
        if ((int) $user->employee_id === $employeeId) return true;

        return Employee::where('id', $employeeId)->where('atasan_id', $user->employee_id)->exists();
    }

    // Reviewer yang ditugaskan bebas (lihat kolom reviewer_id) boleh mengisi progress goal itu,
    // di luar aturan atasan-bawahan biasa — dipakai khusus updateProgress().
    private function isReviewerOf(EmployeeGoal $goal): bool
    {
        $user = auth()->user();
        return $user && $user->employee_id && $goal->reviewer_id && (int) $user->employee_id === (int) $goal->reviewer_id;
    }

    // Null = lihat semua (HR/super-admin, atau GM & Direktur lewat permission "view-all-kpi" —
    // khusus lihat, BUKAN boleh edit KPI orang lain, itu tetap cuma HR lewat canEditKpiFor()).
    // Selain itu, dikembalikan daftar ID: diri sendiri + seluruh bawahan langsung — dipakai buat
    // mempersempit index()/summary() jadi "dashboard tim saya" untuk akun self-input (manajer
    // tanpa bawahan otomatis cuma lihat dirinya sendiri).
    private function scopedEmployeeIds(): ?array
    {
        $user = auth()->user();
        if (!$user || $user->hasRole('super-admin') || $user->can('edit-kpi') || $user->can('view-all-kpi')) {
            return null;
        }
        if (!$user->employee_id) return [];

        $bawahanIds = Employee::where('atasan_id', $user->employee_id)->pluck('id')->toArray();
        // Ditugaskan sebagai reviewer goal orang lain — orang itu ikut masuk cakupan supaya
        // goal yang mau direview kelihatan, walau bukan bawahan langsung.
        $reviewOwnerIds = EmployeeGoal::where('reviewer_id', $user->employee_id)->where('aktif', true)
            ->pluck('employee_id')->toArray();

        return array_values(array_unique([(int) $user->employee_id, ...$bawahanIds, ...$reviewOwnerIds]));
    }

    // Daftar karyawan yang boleh dilihat/dikelola user saat ini, termasuk atasan_id — dipakai
    // buat menyusun tampilan berjenjang (hierarki) di halaman KPI & form Tambah/Edit Goal.
    private function employeesPayload()
    {
        $hoProjectId = Project::where('kode', 'ho')->value('id');
        $scopedIds   = $this->scopedEmployeeIds();

        $employees = Employee::aktif()
            ->where('project_id', $hoProjectId)
            ->when($scopedIds !== null, fn ($q) => $q->whereIn('id', $scopedIds))
            ->with('position')
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'position_id', 'atasan_id'])
            ->map(fn ($e) => [
                'id'           => $e->id,
                'nama_lengkap' => $e->nama_lengkap,
                'jabatan'      => $e->position?->nama_jabatan ?? '-',
                'atasan_id'    => $e->atasan_id,
            ]);

        return [$employees, $scopedIds !== null];
    }

    // Daftar penuh karyawan HO (tanpa dibatasi cakupan atasan-bawahan) — dipakai khusus buat
    // pemilihan "Reviewer / penanggung jawab update progress", supaya penugasannya bebas tidak
    // kaku ke struktur hierarki (misal manajer boleh menunjuk HR atau manajer lain sebagai reviewer).
    private function allEmployeesPayload()
    {
        $hoProjectId = Project::where('kode', 'ho')->value('id');

        return Employee::aktif()
            ->where('project_id', $hoProjectId)
            ->with('position')
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'position_id', 'atasan_id'])
            ->map(fn ($e) => [
                'id'           => $e->id,
                'nama_lengkap' => $e->nama_lengkap,
                'jabatan'      => $e->position?->nama_jabatan ?? '-',
                'atasan_id'    => $e->atasan_id,
            ]);
    }

    private function serializeGoal(EmployeeGoal $g): array
    {
        return [
            'id'                => $g->id,
            'employee_id'       => $g->employee_id,
            'reviewer_id'       => $g->reviewer_id,
            'reviewer_nama'     => $g->reviewer?->nama_lengkap,
            'nama_goal'         => $g->nama_goal,
            'deskripsi'         => $g->deskripsi,
            'siklus'            => $g->siklus,
            'tanggal_mulai'     => $g->tanggal_mulai->format('Y-m-d'),
            'tanggal_selesai'   => $g->tanggal_selesai->format('Y-m-d'),
            'satuan'            => $g->satuan,
            'baseline'          => $g->baseline,
            'target'            => $g->target,
            'progress_sekarang' => $g->progress_sekarang,
            'bobot'             => $g->bobot,
            'catatan'           => $g->catatan,
            'diperbarui_oleh'   => $g->diperbarui_oleh,
            'progress_percent'  => $g->progress_percent,
            'status'            => $g->status,
        ];
    }

    // KPI/Goal khusus untuk karyawan Head Office — tidak terpengaruh project aktif user.
    public function index(Request $request)
    {
        [$employees, $isSelfOnly] = $this->employeesPayload();

        $goals = EmployeeGoal::where('aktif', true)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->orderByDesc('tanggal_mulai')
            ->get()
            ->map(fn ($g) => $this->serializeGoal($g));

        return Inertia::render('Kpi/Index', [
            'employees'    => $employees,
            'goals'        => $goals,
            'is_self_only' => $isSelfOnly,
            'highlight'    => $request->get('highlight'),
        ]);
    }

    // Export Excel — cakupan data sama persis dengan index()/summary() (self + bawahan + goal
    // yang direview, kecuali HR/super-admin/view-all-kpi yang lihat semua) supaya tidak bocor ke
    // goal orang lain yang harusnya tidak boleh diakses user ini.
    // 2 sheet: "Ringkasan" (nilai akhir per karyawan, sama seperti tab Dashboard) dan
    // "Detail Goal" (rincian tiap goal, dengan warna baris mengikuti status-nya).
    public function export(Request $request)
    {
        $hoProjectId = Project::where('kode', 'ho')->value('id');
        $scopedIds   = $this->scopedEmployeeIds();

        $employeesModel = Employee::aktif()->where('project_id', $hoProjectId)
            ->when($scopedIds !== null, fn ($q) => $q->whereIn('id', $scopedIds))
            ->with(['position', 'goals' => fn ($q) => $q->where('aktif', true)])
            ->orderBy('nama_lengkap')->get();

        $statusLabel = [
            'not_updated' => 'Not Updated',
            'on_track'    => 'On Track',
            'off_track'   => 'Off Track',
            'completed'   => 'Completed',
        ];
        $statusColor = [
            'not_updated' => 'E8E8E8',
            'on_track'    => 'C8E6C9',
            'off_track'   => 'FFCDD2',
            'completed'   => 'BBDEFB',
        ];

        $goals = EmployeeGoal::with(['employee.position', 'reviewer'])
            ->where('aktif', true)
            ->whereIn('employee_id', $employeesModel->pluck('id'))
            ->orderBy('employee_id')
            ->orderByDesc('tanggal_mulai')
            ->get();

        $wb = new Spreadsheet();

        // ── SHEET 1: RINGKASAN (nilai akhir per karyawan — sama dengan tab Dashboard) ──
        $sheet1 = $wb->getActiveSheet()->setTitle('Ringkasan');
        $this->kpiExportTitle($sheet1, 'RINGKASAN KPI KARYAWAN — PT. ANDALAS KARYA MULIA (HEAD OFFICE)', 'I', $employeesModel->count());
        $headers1 = [
            'A' => ['No.', 4], 'B' => ['Nama Karyawan', 26], 'C' => ['Jabatan', 24],
            'D' => ['Jml Goal', 10], 'E' => ['Total Bobot %', 12], 'F' => ['Nilai Akhir', 12],
            'G' => ['Not Updated', 12], 'H' => ['On Track / Off Track', 18], 'I' => ['Completed', 11],
        ];
        $this->kpiExportHeaderRow($sheet1, $headers1, 4);
        foreach ($employeesModel as $idx => $e) {
            $row        = 5 + $idx;
            $goalsOwned = $e->goals;
            $totalBobot = $goalsOwned->sum('bobot');
            $skorAkhir  = $totalBobot > 0 ? round($goalsOwned->sum(fn ($g) => $g->progress_percent * $g->bobot / 100), 2) : null;
            $this->kpiExportRow($sheet1, $row, $idx, [
                'A' => $idx + 1,
                'B' => strtoupper($e->nama_lengkap),
                'C' => $e->position?->nama_jabatan ?? '—',
                'D' => $goalsOwned->count(),
                'E' => $totalBobot,
                'F' => $skorAkhir ?? '—',
                'G' => $goalsOwned->filter(fn ($g) => $g->status === 'not_updated')->count(),
                'H' => $goalsOwned->filter(fn ($g) => $g->status === 'on_track')->count() . ' / ' . $goalsOwned->filter(fn ($g) => $g->status === 'off_track')->count(),
                'I' => $goalsOwned->filter(fn ($g) => $g->status === 'completed')->count(),
            ], ['A', 'D', 'E', 'F', 'G', 'H', 'I']);
        }
        $sheet1->setAutoFilter('A4:I4');
        $sheet1->freezePane('B5');
        $sheet1->setShowGridlines(false);

        // ── SHEET 2: DETAIL GOAL ──
        $sheet2 = $wb->createSheet()->setTitle('Detail Goal');
        $this->kpiExportTitle($sheet2, 'DETAIL GOAL KPI — PT. ANDALAS KARYA MULIA (HEAD OFFICE)', 'M', $goals->count());
        $headers2 = [
            'A' => ['No.', 4], 'B' => ['Nama Karyawan', 26], 'C' => ['Jabatan', 24],
            'D' => ['Nama Goal', 34], 'E' => ['Periode', 22], 'F' => ['Satuan', 12],
            'G' => ['Baseline', 12], 'H' => ['Target', 12], 'I' => ['Progress', 12],
            'J' => ['Progress %', 12], 'K' => ['Bobot %', 10], 'L' => ['Status', 13],
            'M' => ['Reviewer', 22],
        ];
        $hRow2 = 4;
        $this->kpiExportHeaderRow($sheet2, $headers2, $hRow2);

        $startRow = 5;
        foreach ($goals as $idx => $g) {
            $row = $startRow + $idx;
            $this->kpiExportRow($sheet2, $row, $idx, [
                'A' => $idx + 1,
                'B' => strtoupper($g->employee?->nama_lengkap ?? '—'),
                'C' => $g->employee?->position?->nama_jabatan ?? '—',
                'D' => $g->nama_goal,
                'E' => $g->tanggal_mulai->format('d M Y') . ' – ' . $g->tanggal_selesai->format('d M Y'),
                'F' => $g->satuan,
                'G' => $g->baseline,
                'H' => $g->target,
                'I' => $g->progress_sekarang,
                'J' => $g->progress_percent . '%',
                'K' => $g->bobot,
                'L' => $statusLabel[$g->status] ?? $g->status,
                'M' => $g->reviewer?->nama_lengkap ?? '—',
            ], ['A', 'F', 'G', 'H', 'I', 'J', 'K', 'L']);
            // Status diwarnai sesuai kondisinya (not updated/on track/off track/completed) —
            // biar kelihatan sekilas tanpa perlu buka grafik terpisah.
            $sheet2->getStyle('L' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($statusColor[$g->status] ?? 'FFFFFF');
        }

        if ($goals->isEmpty()) {
            $sheet2->mergeCells("A{$startRow}:M{$startRow}");
            $sheet2->setCellValue("A{$startRow}", 'Belum ada data goal.');
            $sheet2->getStyle("A{$startRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        $sheet2->freezePane('B5');
        $sheet2->setAutoFilter("A{$hRow2}:M{$hRow2}");
        $sheet2->setShowGridlines(false);
        $sheet2->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $sheet2->getPageSetup()->setFitToPage(true)->setFitToWidth(1)->setFitToHeight(0);

        $wb->setActiveSheetIndex(0);

        $writer = new Xlsx($wb);
        return response()->stream(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="KPI_' . now()->format('Ymd_His') . '.xlsx"',
            'Cache-Control'       => 'max-age=0',
        ]);
    }

    // ── Helper kecil khusus export KPI (judul, header, baris) ──
    private function kpiExportTitle($sheet, string $title, string $lastCol, int $count): void
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

    private function kpiExportHeaderRow($sheet, array $headers, int $hRow): void
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
        $sheet->getRowDimension($hRow)->setRowHeight(28);
    }

    private function kpiExportRow($sheet, int $row, int $idx, array $values, array $centerCols = []): void
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

    // Halaman penuh Tambah Goal (bukan modal) — supaya lega, dengan pemilihan "Goal owner"
    // berjenjang sesuai struktur atasan-bawahan.
    public function create(Request $request)
    {
        [$employees, $isSelfOnly] = $this->employeesPayload();

        return Inertia::render('Kpi/GoalForm', [
            'mode'         => 'add',
            'goal'         => null,
            'employees'    => $employees,
            'all_employees'=> $this->allEmployeesPayload(),
            'is_self_only' => $isSelfOnly,
            'default_employee_id' => $request->get('employee_id'),
        ]);
    }

    // Halaman penuh Edit Goal.
    public function edit(EmployeeGoal $goal)
    {
        if (!$this->canEditKpiFor($goal->employee_id)) {
            abort(403, 'Kamu tidak memiliki akses untuk mengubah goal ini.');
        }

        [$employees, $isSelfOnly] = $this->employeesPayload();

        return Inertia::render('Kpi/GoalForm', [
            'mode'         => 'edit',
            'goal'         => $this->serializeGoal($goal),
            'employees'    => $employees,
            'all_employees'=> $this->allEmployeesPayload(),
            'is_self_only' => $isSelfOnly,
        ]);
    }

    public function storeGoal(Request $request)
    {
        $data = $request->validate([
            'employee_id'      => 'required|exists:employees,id',
            'reviewer_id'      => 'nullable|exists:employees,id',
            'nama_goal'        => 'required|string|max:255',
            'deskripsi'        => 'nullable|string|max:1000',
            'siklus'           => 'required|in:custom,monthly,half_yearly,yearly',
            'tanggal_mulai'    => 'required|date',
            'tanggal_selesai'  => 'required|date|after_or_equal:tanggal_mulai',
            'satuan'           => 'required|in:percentage,number,rupiah',
            'baseline'         => 'required|numeric',
            'target'           => 'required|numeric',
            'bobot'            => 'required|numeric|min:0.01|max:100',
        ]);

        if (!$this->canEditKpiFor((int) $data['employee_id'])) {
            abort(403, 'Kamu tidak memiliki akses untuk menambah goal ini.');
        }

        $goal = EmployeeGoal::create([
            ...$data,
            'progress_sekarang' => $data['baseline'],
            'diperbarui_oleh'   => auth()->user()?->name,
            'aktif'             => true,
        ]);

        ActivityLog::record('create', 'KPI', $goal->employee->nama_lengkap ?? '-', "Tambah goal: {$data['nama_goal']} ({$data['bobot']}%)");

        return redirect()->route('kpi')->with('success', "Goal \"{$goal->nama_goal}\" berhasil ditambahkan.");
    }

    public function updateGoal(Request $request, EmployeeGoal $goal)
    {
        if (!$this->canEditKpiFor($goal->employee_id)) {
            abort(403, 'Kamu tidak memiliki akses untuk mengubah goal ini.');
        }

        $data = $request->validate([
            'reviewer_id'      => 'nullable|exists:employees,id',
            'nama_goal'        => 'required|string|max:255',
            'deskripsi'        => 'nullable|string|max:1000',
            'siklus'           => 'required|in:custom,monthly,half_yearly,yearly',
            'tanggal_mulai'    => 'required|date',
            'tanggal_selesai'  => 'required|date|after_or_equal:tanggal_mulai',
            'satuan'           => 'required|in:percentage,number,rupiah',
            'baseline'         => 'required|numeric',
            'target'           => 'required|numeric',
            'bobot'            => 'required|numeric|min:0.01|max:100',
        ]);

        $goal->update($data);

        ActivityLog::record('update', 'KPI', $goal->employee->nama_lengkap ?? '-', "Update goal: {$goal->nama_goal}");

        return redirect()->route('kpi')->with('success', "Goal \"{$goal->nama_goal}\" berhasil diperbarui.");
    }

    // Update cepat: cuma nilai progress + catatan — dipakai buat "check-in" progress goal
    // tanpa perlu buka form edit lengkap.
    public function updateProgress(Request $request, EmployeeGoal $goal)
    {
        if (!$this->canEditKpiFor($goal->employee_id) && !$this->isReviewerOf($goal)) {
            return response()->json(['ok' => false, 'message' => 'Kamu tidak memiliki akses untuk mengisi progress goal ini.'], 403);
        }

        $data = $request->validate([
            'progress_sekarang' => 'required|numeric|min:0',
            'catatan'           => 'nullable|string|max:1000',
        ]);

        $goal->update([
            'progress_sekarang' => $data['progress_sekarang'],
            'catatan'           => $data['catatan'] ?? $goal->catatan,
            'diperbarui_oleh'   => auth()->user()?->name,
        ]);

        ActivityLog::record('update', 'KPI', $goal->employee->nama_lengkap ?? '-', "Update progress goal: {$goal->nama_goal} -> {$data['progress_sekarang']}");

        return response()->json(['ok' => true, 'goal' => $this->serializeGoal($goal->fresh())]);
    }

    public function destroyGoal(EmployeeGoal $goal)
    {
        if (!$this->canEditKpiFor($goal->employee_id)) {
            return response()->json(['ok' => false, 'message' => 'Kamu tidak memiliki akses untuk menghapus goal ini.'], 403);
        }

        $namaGoal = $goal->nama_goal;
        $namaEmployee = $goal->employee->nama_lengkap ?? '-';
        $goal->delete();

        ActivityLog::record('delete', 'KPI', $namaEmployee, "Hapus goal: {$namaGoal}");

        return response()->json(['ok' => true]);
    }

    // Ringkasan per karyawan: breakdown status goal + 1 skor akhir gabungan (rata-rata progress
    // tiap goal, dibobotkan) — dipakai tab Dashboard.
    public function summary(Request $request)
    {
        $hoProjectId = Project::where('kode', 'ho')->value('id');
        $scopedIds   = $this->scopedEmployeeIds();

        $employees = Employee::aktif()->where('project_id', $hoProjectId)
            ->when($scopedIds !== null, fn ($q) => $q->whereIn('id', $scopedIds))
            ->with('position')->orderBy('nama_lengkap')->get();

        $result = $employees->map(function ($e) {
            $goals = $e->goals()->where('aktif', true)->get();
            $totalBobot = $goals->sum('bobot');
            $skorAkhir  = $totalBobot > 0
                ? round($goals->sum(fn ($g) => $g->progress_percent * $g->bobot / 100), 2)
                : null;

            return [
                'employee_id'   => $e->id,
                'nama_lengkap'  => $e->nama_lengkap,
                'jabatan'       => $e->position?->nama_jabatan ?? '-',
                'total_bobot'   => $totalBobot,
                'skor_akhir'    => $skorAkhir,
                'jml_goal'      => $goals->count(),
                'not_updated'   => $goals->filter(fn ($g) => $g->status === 'not_updated')->count(),
                'on_track'      => $goals->filter(fn ($g) => $g->status === 'on_track')->count(),
                'off_track'     => $goals->filter(fn ($g) => $g->status === 'off_track')->count(),
                'completed'     => $goals->filter(fn ($g) => $g->status === 'completed')->count(),
            ];
        });

        return response()->json(['rows' => $result]);
    }
}
