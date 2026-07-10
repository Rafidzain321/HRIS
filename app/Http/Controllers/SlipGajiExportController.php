<?php
namespace App\Http\Controllers;

use App\Models\EmployeePayroll;
use App\Models\Employee;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Carbon\Carbon;
use Inertia\Inertia;
class SlipGajiExportController extends Controller
{
    // ── Halaman print (Inertia) ──────────────────────────────
    public function print(Request $request, $payrollId)
    {
        $payroll  = EmployeePayroll::with(['employee.position', 'employee.project'])->findOrFail($payrollId);
        $employee = $payroll->employee;
        return Inertia::render('SlipGaji/Print', [
            'payroll' => [
                'id'                  => $payroll->id,
                'gaji_pokok'          => $payroll->gaji_pokok,
                'tunj_jabatan'        => $payroll->tunj_jabatan,
                'tunj_lapangan'       => $payroll->tunj_lapangan,
                'tunj_transport'      => $payroll->tunj_transport,
                'uang_makan'          => $payroll->uang_makan,
                'insentif'            => $payroll->insentif,
                'tunj_perumahan'      => $payroll->tunj_perumahan,
                'tunj_hp'             => $payroll->tunj_hp,
                'tunj_special'        => $payroll->tunj_special,
                'tunj_produksi'       => $payroll->tunj_produksi,
                'kenaikan'            => $payroll->kenaikan,
                'lembur_biasa'        => $payroll->lembur_biasa,
                'lembur_libur'        => $payroll->lembur_libur,
                'lain_lain'           => $payroll->lain_lain,
                'pot_jabatan_pct'     => $payroll->pot_jabatan_pct ?? 0,
                'bpjs_jht'            => $payroll->bpjs_jht,
                'pph21'               => $payroll->pph21,
                'bpjs_kesehatan'      => $payroll->bpjs_kesehatan,
                'bpjs_jp'             => $payroll->bpjs_jp,
                'jumlah_pinjaman'     => $payroll->jumlah_pinjaman,
                'pot_pinjaman'        => $payroll->pot_pinjaman,
                'pot_pinjaman_ke'     => $payroll->pot_pinjaman_ke,
                'sisa_pinjaman'       => $payroll->sisa_pinjaman,
                'pph_ditanggung'      => $payroll->pph_ditanggung ?? 0,
                'pembayaran_jabatan'  => $payroll->pembayaran_jabatan ?? 0,
                'rincian_jam' => $payroll->h_kerja !== null ? [
                    'h_kerja'          => $payroll->h_kerja,
                    'h_sabtu'          => $payroll->h_sabtu,
                    'h_minggu_libur'   => $payroll->h_minggu_libur,
                    'total_jam_ot_15x' => $payroll->total_jam_ot_15x,
                    'total_jam_ot_2x'  => $payroll->total_jam_ot_2x,
                ] : null,
            ],
            'employee' => [
                'id_badge'       => $employee->id_badge,
                'nama_lengkap'   => $employee->nama_lengkap,
                'jabatan'        => $employee->position?->nama_jabatan ?? '-',
                'project_nama'   => $employee->project?->nama,
                'no_rekening'    => $employee->no_rekening,
                'ptkp'           => $employee->ptkp,
                'tanggal_masuk'  => $employee->tanggal_masuk?->format('d M Y'),
            ],
            'periode' => [
                'tahun' => $payroll->tahun,
                'bulan' => $payroll->bulan,
            ],
        ]);
    }
    // ── Export Excel ─────────────────────────────────────────
    public function exportExcel($payrollId)
    {
        $payroll  = EmployeePayroll::with(['employee.position', 'employee.project'])->findOrFail($payrollId);
        $employee = $payroll->employee;

        $bulanNama    = ['','Januari','Februari','Maret','April','Mei','Juni',
                         'Juli','Agustus','September','Oktober','November','Desember'];
        $periodeLabel = $bulanNama[$payroll->bulan] . ' ' . $payroll->tahun;

        // Hitung nilai
        $gajiPokok   = $payroll->gaji_pokok ?? 0;
        $tunjJabatan = $payroll->tunj_jabatan ?? 0;
        $ttt = collect([
            $payroll->tunj_lapangan, $payroll->tunj_transport,
            $payroll->uang_makan, $payroll->insentif,
            $payroll->tunj_perumahan, $payroll->tunj_hp,
            $payroll->tunj_special, $payroll->tunj_produksi,
            $payroll->kenaikan,
        ])->sum();
        $lembur      = ($payroll->lembur_biasa ?? 0) + ($payroll->lembur_libur ?? 0) + ($payroll->lain_lain ?? 0);
        $gajiSebulan = $gajiPokok + $tunjJabatan + $ttt + $lembur;
        $potJabatan  = $payroll->pot_jabatan_pct
            ? round($gajiSebulan * $payroll->pot_jabatan_pct / 100) : 0;
        $pengKotor   = $gajiSebulan - $potJabatan;
        $totalPot    = ($payroll->bpjs_jht ?? 0) + ($payroll->pph21 ?? 0)
                     + ($payroll->bpjs_kesehatan ?? 0) + ($payroll->bpjs_jp ?? 0);
        $pengBersih  = $pengKotor - $totalPot;
        $potPinjaman = $payroll->pot_pinjaman ?? 0;
        $netto       = $pengBersih + ($payroll->pph_ditanggung ?? 0)
                     + ($payroll->pembayaran_jabatan ?? 0) - $potPinjaman;

        $ss = new Spreadsheet();
        $ws = $ss->getActiveSheet();
        $ws->setTitle('Slip Gaji');
        $ws->getColumnDimension('A')->setWidth(4);
        $ws->getColumnDimension('B')->setWidth(3);
        $ws->getColumnDimension('C')->setWidth(28);
        $ws->getColumnDimension('D')->setWidth(3);
        $ws->getColumnDimension('E')->setWidth(13);
        $ws->getColumnDimension('F')->setWidth(3);
        $ws->getColumnDimension('G')->setWidth(13);

        $f = fn($n) => number_format((float)($n ?? 0), 0, ',', '.');

        $row = 1;

        // Header perusahaan
        $ws->mergeCells("A{$row}:G{$row}");
        $ws->setCellValue("A{$row}", 'PT. ANDALAS KARYA MULIA');
        $ws->getStyle("A{$row}")->getFont()->setBold(true)->setSize(11);
        $ws->getRowDimension($row)->setRowHeight(16);
        $row++;
        $ws->mergeCells("A{$row}:G{$row}");
        $ws->setCellValue("A{$row}", 'Jl. Wonosari, Komplek Wonosari Regency Blok B No.1, Pekanbaru – Riau');
        $ws->getStyle("A{$row}")->getFont()->setSize(8)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF555555'));
        $row++;

        // Judul
        $ws->mergeCells("A{$row}:G{$row}");
        $ws->setCellValue("A{$row}", "── SLIP GAJI ── {$periodeLabel}");
        $ws->getStyle("A{$row}")->getFont()->setBold(true)->setSize(10);
        $ws->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getStyle("A{$row}:G{$row}")->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('1E2436');
        $ws->getStyle("A{$row}")->getFont()->getColor()->setRGB('E8A020');
        $row++;

        $ws->getRowDimension($row)->setRowHeight(4);
        $row++;
        // Identitas — dengan No. Rekening
        $identitas = [
            ['No. Register',    $employee->id_badge],
            ['Nama Karyawan',   $employee->nama_lengkap],
            ['Jabatan',         $employee->position?->nama_jabatan ?? '-'],
            ['Daerah Operasi',  $employee->project?->nama ?? '-'],
            ['No. Rekening',    $employee->no_rekening ?? '-'],
            ['Mulai Bergabung', $employee->tanggal_masuk?->format('d M Y') ?? '-'],
        ];
        foreach ($identitas as [$lbl, $val]) {
            $ws->setCellValue("A{$row}", $lbl);
            $ws->setCellValue("B{$row}", ':');
            $ws->mergeCells("C{$row}:D{$row}");
            $ws->setCellValue("C{$row}", $val);
            $ws->getStyle("C{$row}")->getFont()->setBold(true);
            $ws->getStyle("A{$row}:D{$row}")->getFont()->setSize(9);
            $row++;
        }
        // Netto box di kanan identitas
        $nRow = $row - 5;
        $ws->mergeCells("E{$nRow}:G{$nRow}");
        $ws->setCellValue("E{$nRow}", 'NETTO DITERIMA');
        $ws->getStyle("E{$nRow}")->getFont()->setSize(8)->setBold(true);
        $ws->getStyle("E{$nRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $nRow++;
        $ws->mergeCells("E{$nRow}:G{$nRow}");
        $ws->setCellValue("E{$nRow}", 'Rp ' . $f($netto));
        $ws->getStyle("E{$nRow}")->getFont()->setSize(14)->setBold(true);
        $ws->getStyle("E{$nRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getStyle("E{$nRow}:G{$nRow}")->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        $row++;
        // Helper functions
        $addRow = function($no, $nama, $nilai, $total = null) use ($ws, &$row, $f) {
            $ws->setCellValue("A{$row}", $no);
            $ws->setCellValue("C{$row}", $nama);
            if ($nilai !== null) {
                $ws->setCellValue("E{$row}", 'Rp ' . $f($nilai));
                $ws->getStyle("E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
            $ws->setCellValue("F{$row}", '=');
            if ($total !== null) {
                $ws->setCellValue("G{$row}", 'Rp ' . $f($total));
                $ws->getStyle("G{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
            $ws->getStyle("A{$row}:G{$row}")->getFont()->setSize(9);
            $row++;
        };
        $addSection = function($letter, $title) use ($ws, &$row) {
            $ws->setCellValue("A{$row}", $letter);
            $ws->mergeCells("C{$row}:G{$row}");
            $ws->setCellValue("C{$row}", $title);
            $ws->getStyle("A{$row}:G{$row}")->getFont()->setBold(true)->setSize(9);
            $ws->getStyle("A{$row}:G{$row}")->getFill()->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('F0F0F0');
            $row++;
        };
        $addTotal = function($label, $nilai, $bold = false) use ($ws, &$row, $f) {
            $ws->mergeCells("A{$row}:F{$row}");
            $ws->setCellValue("A{$row}", $label);
            $ws->setCellValue("G{$row}", 'Rp ' . $f($nilai));
            $ws->getStyle("A{$row}:G{$row}")->getFont()->setBold($bold)->setSize(9);
            $ws->getStyle("G{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            if ($bold) {
                $ws->getStyle("A{$row}:G{$row}")->getBorders()->getTop()
                    ->setBorderStyle(Border::BORDER_MEDIUM);
                $ws->getStyle("A{$row}:G{$row}")->getBorders()->getBottom()
                    ->setBorderStyle(Border::BORDER_MEDIUM);
            } else {
                $ws->getStyle("A{$row}:G{$row}")->getBorders()->getTop()
                    ->setBorderStyle(Border::BORDER_THIN);
            }
            $row++;
        };

        $addSection('A.', 'PEROLEHAN');
        $addRow('1.', 'Gaji Pokok / Upah', $gajiPokok, $gajiPokok);
        $addRow('2.', 'Tunjangan Tetap', null, null);
        $addRow('',   '— Tunj. Jabatan', $tunjJabatan, $tunjJabatan);
        $addSection('B.', 'TUNJANGAN TIDAK TETAP');
        foreach ([
            ['1.','T. Lapangan', $payroll->tunj_lapangan],
            ['2.','T. Transport', $payroll->tunj_transport],
            ['3.','U. Makan', $payroll->uang_makan],
            ['4.','Insentif', $payroll->insentif],
            ['5.','T. Perumahan', $payroll->tunj_perumahan],
            ['6.','T. HP', $payroll->tunj_hp],
            ['7.','T. Special', $payroll->tunj_special],
            ['8.','T. Produksi', $payroll->tunj_produksi],
            ['9.','Kenaikan', $payroll->kenaikan],
        ] as [$no, $nama, $val]) {
            if ($val) $addRow($no, $nama, $val, $val);
        }

        $addSection('C.', 'LAIN-LAIN');
        $addRow('1.', 'Lembur Hari Biasa', $payroll->lembur_biasa ?? 0, $payroll->lembur_biasa ?? 0);
        $addRow('2.', 'Lembur Libur Nasional/Minggu', $payroll->lembur_libur ?? 0, $payroll->lembur_libur ?? 0);
        if ($payroll->lain_lain) $addRow('3.', 'Lain-Lain', $payroll->lain_lain, $payroll->lain_lain);

        $addTotal('GAJI SEBULAN', $gajiSebulan, true);

        $addSection('D.', 'POTONGAN JABATAN');
        $addRow('', "Gaji × {$payroll->pot_jabatan_pct}%", $gajiSebulan, $potJabatan);
        $addTotal('PENGHASILAN KOTOR SEBULAN', $pengKotor);
        $addSection('E.', 'POTONGAN WAJIB');
        $addRow('1.', 'PTKP — ' . ($employee->ptkp ?? '—'), null, null);
        $addRow('2.', 'BPJS TK : JHT (2%)', null, $payroll->bpjs_jht ?? 0);
        $addRow('3.', 'PPh 21 Sebulan', null, $payroll->pph21 ?? 0);
        $addRow('4.', 'BPJS Kesehatan (1%)', null, $payroll->bpjs_kesehatan ?? 0);
        $addRow('5.', 'BPJS JP (1%)', null, $payroll->bpjs_jp ?? 0);
        $addTotal('TOTAL POTONGAN SEBULAN', $totalPot);
        $addTotal('PENGHASILAN BERSIH SETELAH POT. WAJIB', $pengBersih);

        $addSection('F.', 'PINJAMAN BULAN INI');
        $addRow('1.', 'Jumlah Pinjaman', $payroll->jumlah_pinjaman ?? 0, null);
        $addRow('2.', 'Pot / Bulan', $payroll->pot_pinjaman ?? 0, null);
        $addRow('3.', 'Pot. Ke', $payroll->pot_pinjaman_ke ?? 0, null);
        $addRow('4.', 'Sisa Pinjaman', $payroll->sisa_pinjaman ?? 0, null);
        $addTotal('TOTAL POT. PINJAMAN SEBULAN', $potPinjaman);

        $addRow('G.', 'PPh DITANGGUNG PEMERINTAH', null, $payroll->pph_ditanggung ?? 0);
        $addRow('H.', 'PEMBAYARAN JABATAN', null, $payroll->pembayaran_jabatan ?? 0);
        $addTotal('PENGHASILAN BERSIH (NETTO) SETELAH POT. PINJAMAN', $netto, true);

        // ── TTD ──────────────────────────────────────────────────
        $row += 1;
        $ws->setCellValue("A{$row}", "Pekanbaru, " . now()->isoFormat('D MMMM YYYY'));
        $ws->getStyle("A{$row}")->getFont()->setSize(8);
        $row += 2;
        foreach (['Disetujui Oleh,', 'Dibayar Oleh,', 'Diterima Oleh,'] as $i => $lbl) {
            $col = ['A', 'C', 'E'][$i];
            $ws->setCellValue("{$col}{$row}", $lbl);
            $ws->getStyle("{$col}{$row}")->getFont()->setBold(true)->setSize(8);
        }
        $row += 4;
        $names = ['H. Syahrul Akmal', 'Yulhamdani', $employee->nama_lengkap];
        $roles  = ['Direktur Utama', 'Finance', $employee->position?->nama_jabatan ?? ''];
        foreach ([['A','C'],['C','E'],['E','G']] as $i => [$from, $to]) {
            $ws->getStyle("{$from}{$row}:{$to}{$row}")->getBorders()->getTop()
                ->setBorderStyle(Border::BORDER_THIN);
            $ws->setCellValue("{$from}{$row}", $names[$i]);
            $ws->getStyle("{$from}{$row}")->getFont()->setBold(true)->setSize(8);
            $row++;
            $ws->setCellValue("{$from}{$row}", $roles[$i]);
            $ws->getStyle("{$from}{$row}")->getFont()->setSize(8)->setItalic(true);
            $row--;
        }
        $row += 2;

        // ── No. Rekening di bawah TTD (ganti Note) ───────────────
        $ws->mergeCells("A{$row}:G{$row}");
        $ws->setCellValue("A{$row}", 'No. Rekening: ' . ($employee->no_rekening ?? '-'));
        $ws->getStyle("A{$row}")->getFont()->setSize(8)->setBold(true);
        $ws->getStyle("A{$row}:G{$row}")->getBorders()->getTop()
            ->setBorderStyle(Border::BORDER_DASHED);

        // Page setup A5
        $ws->getPageSetup()
            ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A5)
            ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT)
            ->setFitToPage(true)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $ws->getPageMargins()->setTop(0.3)->setRight(0.3)->setBottom(0.3)->setLeft(0.3);

        $nama_file = "SlipGaji_{$employee->id_badge}_{$periodeLabel}.xlsx";
        $nama_file = str_replace(' ', '_', $nama_file);

        $writer = new Xlsx($ss);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename=\"{$nama_file}\"");
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
        exit;
    }
}