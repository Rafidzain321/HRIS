<?php
namespace App\Http\Controllers;

use App\Models\Holiday;
use App\Models\Project;
use App\Models\Timesheet;
use Carbon\Carbon;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class PayrollExportController extends Controller
{
    // ── Palet warna — mengikuti format slip gaji Excel asli (GIAM/Khawista/NK) ──
    // Contoh asli hanya memakai: kuning lembut, kuning terang, hijau, dan putih.
    const C_TITLE_BG   = 'FFFFFF';
    const C_TITLE_TXT  = '000000';
    const C_IDENTITAS  = 'FFFFFF'; // putih — No, Nama, Jabatan, NIK, Rekening
    const C_TUNJ_TETAP = 'FFFFFF'; // data putih (header dipaksa kuning oleh block header)
    const C_TTT        = 'FFFFFF';
    const C_LEMBUR     = 'FFFFFF';
    const C_BPJS       = 'FFFFFF';
    const C_ALPA       = 'FFFFFF';
    const C_BERSIH     = 'FFFFFF';
    const C_HDR_TXT    = '000000';
    const C_TS_SUN     = 'FEE2E2';
    const C_TS_SAT     = 'EEF2FF';
    const C_TS_HOL     = 'F3E8FF';
    const C_TS_IZIN    = 'BDD7EE';
    const C_TS_SAKIT   = 'FFD966';
    const C_TS_ALPA    = 'FF7C80';
    const C_TS_CUTI    = 'C6EFCE';
    const C_TS_STB     = 'E8E8FF';
    const C_TS_HADIR   = 'CCFFCC';

    private function thinBorder(): array
    {
        return ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]]];
    }

    // ── Style untuk sel header (label kolom) ──
    private function hdr($sheet, string $cell, string $label, string $bg): void
    {
        $sheet->setCellValue($cell, $label);
        $sheet->getStyle($cell)->applyFromArray(array_merge([
            'font'      => ['name' => 'Arial', 'size' => 9, 'bold' => true, 'color' => ['rgb' => self::C_HDR_TXT]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ], $this->thinBorder()));
    }

    // ── Style sel data biasa ──
    private function cell($sheet, string $cell, string $bg, bool $bold = false, string $align = Alignment::HORIZONTAL_RIGHT): void
    {
        $sheet->getStyle($cell)->applyFromArray(array_merge([
            'font'      => ['name' => 'Arial', 'size' => 9, 'bold' => $bold],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
            'alignment' => ['horizontal' => $align, 'vertical' => Alignment::VERTICAL_CENTER],
        ], $this->thinBorder()));
    }

    private function rupiah($sheet, string $cellRef): void
    {
        $sheet->getStyle($cellRef)->getNumberFormat()->setFormatCode('#,##0;(#,##0);"-"');
    }

    // ════════════════════════════════════════════════════════════
    // EXPORT DATA GAJI — Excel
    // ════════════════════════════════════════════════════════════
    public function dataGaji(Request $request, PayrollController $payroll)
    {
        $tahun     = (int) $request->get('tahun', now()->year);
        $bulan     = (int) $request->get('bulan', now()->month);
        $projectId = $this->activeProjectId();

        $built    = $payroll->buildPayrollRows($tahun, $bulan, $projectId);
        $rows     = $built['rows'];
        $tttItems = $built['ttt_items'];

        $project   = $projectId ? Project::find($projectId) : null;
        $isMd      = $project?->tipe_gaji === 'md';
        $bulanNama = $this->bulanNamaList();
        $namaBulan = $bulanNama[$bulan] ?? '';
        $namaProj  = $project?->nama ?? 'Semua Project';

        // Kolom TTT default (selalu tampil walau kosong, sesuai keputusan)
        $tttDefaultKeys = [
            'tunj_makan'         => 'Tunj Makan',
            'tunj_produksi'      => 'Tunj Produksi',
            'tunj_lapangan'      => 'Tunj Lapangan',
            'tunj_kehadiran'     => 'Tunj Kehadiran',
            'tunj_pulsa'         => 'Tunj Pulsa',
            'kompensasi_kontrak' => 'Komp. Kontrak',
            'insentif'           => 'Insentif',
            'com_day'            => 'Com Day',
        ];
        $customTttKeys = $tttItems->where('is_default', false)->pluck('label', 'key')->toArray();
        $allTttKeysRaw = $tttDefaultKeys + $customTttKeys;

        $allTttKeys = [];
        foreach ($allTttKeysRaw as $key => $label) {
            $totalNilai = collect($rows)->sum(fn($r) => (float) ($r[$key] ?? 0));
            if ($totalNilai > 0) {
                $allTttKeys[$key] = $label;
            }
        }

        $hasSubGroup     = $rows && collect($rows)->pluck('sub_group')->filter()->isNotEmpty();
        $hasPotOksigen   = collect($rows)->sum('pot_tabung_oksigen') > 0;

        // ── Susun kolom (urutan persis seperti contoh Excel) ──
        // Format kolom: [label, width, warna, grup, sub-grup]
        //   grup     = judul header tingkat-1 (baris paling atas), null jika kolom identitas (merge penuh 3 baris)
        //   sub-grup = judul header tingkat-2, null jika langsung di bawah grup
        $cols = [];
        $cols['no']           = ['No.',           4,  self::C_IDENTITAS, null, null];
        $cols['nama_lengkap'] = ['Nama Karyawan', 20, self::C_IDENTITAS, null, null];
        $cols['jabatan']      = ['Jabatan',       12, self::C_IDENTITAS, null, null];
        if ($hasSubGroup) {
            $cols['sub_group'] = ['Sub Group',     9, self::C_IDENTITAS, null, null];
        }
        $cols['id_badge']     = ['Badge',         11, self::C_IDENTITAS, null, null];
        $cols['no_ktp']       = ['No. KTP',       12, self::C_IDENTITAS, null, null];
        $cols['ptkp']         = ['PTKP',          5,  self::C_IDENTITAS, null, null];
        $cols['no_bpjs_tk']   = ['No. BPJS TK',   11, self::C_IDENTITAS, null, null];
        $cols['no_bpjs_kes']  = ['No. BPJS Kes',  11, self::C_IDENTITAS, null, null];
        $cols['nama_bank']    = ['Nama Bank',     8,  self::C_IDENTITAS, null, null];
        $cols['no_rekening']  = ['No. Rekening',  12, self::C_IDENTITAS, null, null];
        $cols['gaji_pokok']   = ['Gaji Pokok',    10, self::C_IDENTITAS, null, null];

        // ── Grup: TUNJANGAN ──
        $cols['tunj_tetap']     = ['Tunj Tetap',  9, self::C_TUNJ_TETAP, 'Tunjangan', 'Tunjangan Tetap'];
        $cols['kompensasi_pwt'] = ['Komp. PWT',   9, self::C_TUNJ_TETAP, 'Tunjangan', 'Tunjangan Tetap'];
        $cols['upah_penuh']     = ['Upah Penuh', 10, self::C_TUNJ_TETAP, 'Tunjangan', null];
        foreach ($allTttKeys as $key => $label) {
            $cols[$key] = [$label, 9, self::C_TTT, 'Tunjangan', 'Tunjangan Tidak Tetap'];
        }
        if ($isMd) {
            $cols['ttt_perhari']      = ['TTT / Hari',      9, self::C_TTT, 'Tunjangan', 'Tunjangan Tidak Tetap'];
            $cols['ttt_total']        = ['TTT Total',      10, self::C_TTT, 'Tunjangan', 'Tunjangan Tidak Tetap'];
            $cols['tunj_makan_total'] = ['Tunj Makan Tot', 10, self::C_TTT, 'Tunjangan', 'Tunjangan Tidak Tetap'];
            $cols['com_day_total']    = ['Com Day Tot',    10, self::C_TTT, 'Tunjangan', 'Tunjangan Tidak Tetap'];
        }

        // ── Grup: LEMBUR & GAJI KOTOR (tanpa sub-grup) ──
        $cols['jml_jam_lembur'] = ['Jml Jam Lembur', 8,  self::C_LEMBUR, null, null];
        $cols['upah_lembur']    = ['Upah Lembur',    10, self::C_LEMBUR, null, null];
        $cols['h_kerja']        = ['H. Kerja',       7,  self::C_LEMBUR, null, null];
        if ($hasPotOksigen) {
            $cols['pot_tabung_oksigen'] = ['Pot. Oksigen', 9, self::C_TTT, null, null];
        }
        $cols['gaji_kotor']     = ['Gaji Kotor',     11, self::C_LEMBUR, null, null];

        // ── Grup: POTONGAN ──
        $cols['potongan_jht']     = ['BPJS TK-JHT (2%)',      9, self::C_BPJS, 'Potongan', null];
        $cols['potongan_pensiun'] = ['BPJS TK-Pensiun (1%)',  9, self::C_BPJS, 'Potongan', null];
        $cols['potongan_kes']     = ['BPJS Kesehatan (1%)',   9, self::C_BPJS, 'Potongan', null];
        $cols['potongan_alpa']    = ['Alpa / Pinjaman',       9, self::C_TTT,  'Potongan', null];
        if (collect($rows)->sum('potongan_insentif') > 0) {
            $cols['potongan_insentif'] = ['Pot. Insentif',    9, self::C_TTT,  'Potongan', null];
        }
        $cols['kekurangan_bulan_lalu'] = ['Kekurangan Gaji Bln Lalu', 10, self::C_IDENTITAS, null, null];
        $cols['gaji_bersih']      = ['Gaji Bersih Setelah Potongan', 12, self::C_BERSIH, null, null];

        // ── Grup: ABSENSI ──
        $cols['izin']  = ['Izin',  5, self::C_IDENTITAS, 'Absensi', null];
        $cols['sakit'] = ['Sakit', 5, self::C_IDENTITAS, 'Absensi', null];
        $cols['alpa']  = ['Alpa',  5, self::C_IDENTITAS, 'Absensi', null];
        $cols['cuti']  = ['Cuti',  5, self::C_IDENTITAS, 'Absensi', null];

        // ── Build workbook ──
        $wb    = new Spreadsheet();
        $sheet = $wb->getActiveSheet()->setTitle('DATA GAJI');

        $totalCol = count($cols);
        $lastColLetter = Coordinate::stringFromColumnIndex($totalCol);

        // Judul
        $sheet->mergeCells("A1:{$lastColLetter}1");
        $sheet->setCellValue('A1', 'GAJI ' . strtoupper($namaBulan) . " ( PERIODE 01-" . Carbon::create($tahun, $bulan)->daysInMonth . " " . strtoupper($namaBulan) . " {$tahun} )");
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['name' => 'Arial', 'size' => 16, 'bold' => true, 'color' => ['rgb' => self::C_TITLE_TXT]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::C_TITLE_BG]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(26);

        $sheet->mergeCells("A2:{$lastColLetter}2");
        $sheet->setCellValue('A2', 'Karyawan PT. Andalas Karya Mulia — Project ' . strtoupper($namaProj));
        $sheet->getStyle('A2')->applyFromArray([
            'font'      => ['name' => 'Arial', 'size' => 12, 'bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(20);

        $sheet->mergeCells("A3:{$lastColLetter}3");
        $sheet->setCellValue('A3', 'Diekspor pada: ' . now()->format('d M Y H:i') . ' WIB  |  Total: ' . count($rows) . ' karyawan');
        $sheet->getStyle('A3')->applyFromArray([
            'font'      => ['name' => 'Arial', 'size' => 9, 'italic' => true, 'color' => ['rgb' => '666666']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(3)->setRowHeight(14);
        $sheet->getRowDimension(4)->setRowHeight(6);

        // ── Header 3 tingkat (baris 5 = grup, 6 = sub-grup, 7 = detail) ──
        $rGroup = 5; $rSub = 6; $rDetail = 7;
        $colList = array_values($cols);
        $n = count($colList);

        // set lebar kolom + isi baris detail (tingkat-3)
        for ($i = 0; $i < $n; $i++) {
            [$label, $width, $bg] = $colList[$i];
            $colL = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->getColumnDimension($colL)->setWidth($width);
            $this->hdr($sheet, $colL . $rDetail, $label, $bg);
        }

        // Merge & isi grup (tingkat-1) dan sub-grup (tingkat-2)
        $i = 0;
        while ($i < $n) {
            [$label, $width, $bg, $group] = $colList[$i];
            $colL = Coordinate::stringFromColumnIndex($i + 1);

            if ($group === null) {
                // Kolom identitas → merge vertikal penuh (baris 5-7)
                $sheet->mergeCells("{$colL}{$rGroup}:{$colL}{$rDetail}");
                // pindahkan label detail ke sel merge teratas
                $sheet->setCellValue("{$colL}{$rGroup}", $label);
                $sheet->setCellValue("{$colL}{$rDetail}", null);
                $this->hdr($sheet, "{$colL}{$rGroup}", $label, $bg);
                $i++;
                continue;
            }

            // Cari rentang kolom yang punya grup sama (berturut-turut)
            $j = $i;
            while ($j < $n && $colList[$j][3] === $group) $j++;
            $startL = Coordinate::stringFromColumnIndex($i + 1);
            $endL   = Coordinate::stringFromColumnIndex($j);
            // merge baris grup (tingkat-1) sepanjang rentang
            if ($startL === $endL) $sheet->mergeCells("{$startL}{$rGroup}:{$startL}{$rGroup}");
            else $sheet->mergeCells("{$startL}{$rGroup}:{$endL}{$rGroup}");
            $this->hdr($sheet, "{$startL}{$rGroup}", $group, $bg);

            // Dalam rentang grup, kelompokkan sub-grup
            $k = $i;
            while ($k < $j) {
                $subName = $colList[$k][4];
                $subColor = $colList[$k][2];
                $m = $k;
                while ($m < $j && $colList[$m][4] === $subName) $m++;
                $sL = Coordinate::stringFromColumnIndex($k + 1);
                $eL = Coordinate::stringFromColumnIndex($m);
                if ($subName === null) {
                    // tidak ada sub-grup → merge vertikal baris 6-7 (sub + detail)
                    for ($x = $k; $x < $m; $x++) {
                        $xL = Coordinate::stringFromColumnIndex($x + 1);
                        $sheet->mergeCells("{$xL}{$rSub}:{$xL}{$rDetail}");
                        // label detail sudah diisi, pindahkan ke sel merge
                        $lbl = $colList[$x][0];
                        $sheet->setCellValue("{$xL}{$rSub}", $lbl);
                        $sheet->setCellValue("{$xL}{$rDetail}", null);
                        $this->hdr($sheet, "{$xL}{$rSub}", $lbl, $colList[$x][2]);
                    }
                } else {
                    // ada sub-grup → merge horizontal baris 6
                    if ($sL === $eL) $sheet->mergeCells("{$sL}{$rSub}:{$sL}{$rSub}");
                    else $sheet->mergeCells("{$sL}{$rSub}:{$eL}{$rSub}");
                    $this->hdr($sheet, "{$sL}{$rSub}", $subName, $subColor);
                }
                $k = $m;
            }
            $i = $j;
        }

        $sheet->getRowDimension($rGroup)->setRowHeight(18);
        $sheet->getRowDimension($rSub)->setRowHeight(18);
        $sheet->getRowDimension($rDetail)->setRowHeight(40);
        $hRow = $rDetail;

        $sheet->getStyle("A{$rGroup}:{$lastColLetter}{$rDetail}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF99']],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
            ],
        ]);

        // Data rows
        $startRow = $rDetail + 1;
        $moneyCols = [
            'gaji_pokok','tunj_tetap','kompensasi_pwt','upah_penuh',
            'jml_jam_lembur','upah_lembur','gaji_kotor',
            'potongan_jht','potongan_pensiun','potongan_kes','potongan_alpa',
            'potongan_insentif','pot_tabung_oksigen','kekurangan_bulan_lalu','gaji_bersih',
            'ttt_perhari','ttt_total','tunj_makan_total','com_day_total',
        ];
        foreach (array_keys($allTttKeys) as $k) $moneyCols[] = $k;

        // ── Build peta kolom → letter, untuk keperluan formula ──
        $colLetter = [];
        $ci = 1;
        foreach ($cols as $key => $meta) {
            $colLetter[$key] = Coordinate::stringFromColumnIndex($ci);
            $ci++;
        }

        // Helper untuk generate ref formula: $L('gaji_pokok', $r) → "K8" (misal)
        $L = function (string $key, int $rowNum) use ($colLetter) {
            return isset($colLetter[$key]) ? $colLetter[$key] . $rowNum : '0';
        };

        // Semua key TTT (untuk SUM di gaji kotor)
        $tttKeysAll = array_keys($allTttKeys);

        foreach ($rows as $idx => $row) {
            $r = $startRow + $idx;
            $projectKode = strtolower($row['project_kode'] ?? '');
            $subGrp      = strtolower($row['sub_group']    ?? '');
            $isMdRow     = $projectKode === 'md' || ($isMd && !$projectKode);

            // ── Ref shortcuts ──
            $refGP    = $L('gaji_pokok', $r);
            $refTT    = $L('tunj_tetap', $r);
            $refKP    = $L('kompensasi_pwt', $r);
            $refUP    = $L('upah_penuh', $r);
            $refUL    = $L('upah_lembur', $r);
            $refKekur = $L('kekurangan_bulan_lalu', $r);
            $refAlpa  = $L('alpa', $r);
            $refIzin  = $L('izin', $r);
            $refSakit = $L('sakit', $r);
            $refCuti  = $L('cuti', $r);
            $refJht   = $L('potongan_jht', $r);
            $refPen   = $L('potongan_pensiun', $r);
            $refKes   = $L('potongan_kes', $r);
            $refPotAl = $L('potongan_alpa', $r);
            $refPotIn = $L('potongan_insentif', $r);
            $refPotOk = $L('pot_tabung_oksigen', $r);
            $refGajiK = $L('gaji_kotor', $r);
            $refInsen = $L('insentif', $r);
            $refTLap  = $L('tunj_lapangan', $r);
            $refTPls  = $L('tunj_pulsa', $r);
            $refStb   = $L('stb', $r);

            // Range TTT untuk SUM (dari kolom pertama TTT sampai kolom terakhir TTT)
            $tttRefs = [];
            foreach ($tttKeysAll as $k) {
                if (isset($colLetter[$k])) $tttRefs[] = $colLetter[$k] . $r;
            }
            $sumTtt = empty($tttRefs) ? '0' : implode('+', $tttRefs);

            // ── Formula per-project ──
            $izinDipotong = !in_array($projectKode, ['khawista', 'purnama'], true);
            $formulaAlpa  = $izinDipotong
                ? "={$refUP}/25*({$refAlpa}+{$refIzin})"
                : "={$refUP}/25*{$refAlpa}";

            $formulaPotIns = '';
            if ($projectKode === 'khawista' && $subGrp === 'construction') {
                $formulaPotIns = "={$refInsen}/25*({$refIzin}+{$refSakit}+{$refCuti})";
            } elseif ($projectKode === 'khawista' && $subGrp === 'piling') {
                $formulaPotIns = "={$refTLap}/25*{$refIzin}";
            } elseif ($projectKode === 'nk') {
                $formulaPotIns = isset($colLetter['stb'])
                    ? "={$refInsen}/25*({$refIzin}+{$refSakit}+{$refCuti}+{$refStb})"
                    : "={$refInsen}/25*({$refIzin}+{$refSakit}+{$refCuti})";
            }

            // Formula Gaji Kotor
            if ($isMdRow) {
                $refUBasic = $L('u_basic', $r) ?: '0';
                $refUKerja = $L('u_kerja', $r) ?: '0';
                $refCDayTot = $L('com_day_total', $r) ?: '0';
                $formulaGajiKotor = "={$refUBasic}+{$refKP}+{$refUKerja}+{$refCDayTot}+{$refUL}+{$refTPls}+{$refKekur}";
            } else {
                $formulaGajiKotor = "={$refUP}+{$refKP}+({$sumTtt})+{$refUL}";
                if (isset($colLetter['uang_hadir'])) {
                    $refUH = $colLetter['uang_hadir'] . $r;
                    if (($row['total_lembur_flat'] ?? 0) > 0 || ($row['uang_hadir'] ?? 0) > 0) {
                        $formulaGajiKotor .= "+{$refUH}";
                    }
                }
            }

            // Formula Gaji Bersih
            $bersihParts = ["{$refGajiK}", "-{$refJht}", "-{$refPen}", "-{$refKes}", "-{$refPotAl}"];
            if (isset($colLetter['potongan_insentif'])) $bersihParts[] = "-{$refPotIn}";
            if (isset($colLetter['pot_tabung_oksigen']) && ($row['pot_tabung_oksigen'] ?? 0) > 0) {
                $bersihParts[] = "-{$refPotOk}";
            }
            if ($projectKode === 'nk') {
                $bersihParts[] = "+{$refKekur}";
            }
            $formulaGajiBersih = '=' . implode('', $bersihParts);

            // ── Isi setiap sel ──
            $c = 1;
            foreach ($cols as $key => [, , $bg]) {
                $colL   = Coordinate::stringFromColumnIndex($c);
                $cellRef = $colL . $r;
                $isMoney = in_array($key, $moneyCols, true);
                $align   = $key === 'no' ? Alignment::HORIZONTAL_CENTER
                    : ($key === 'nama_lengkap' || $key === 'jabatan' ? Alignment::HORIZONTAL_LEFT
                    : ($isMoney ? Alignment::HORIZONTAL_RIGHT : Alignment::HORIZONTAL_CENTER));

                $value = match ($key) {
                    'no'           => $idx + 1,
                    'nama_lengkap' => strtoupper($row['nama_lengkap'] ?? '—'),
                    'sub_group'    => $row['sub_group'] ? ucfirst($row['sub_group']) : '—',
                    default        => $row[$key] ?? null,
                };

                if (in_array($key, ['id_badge', 'no_ktp', 'no_bpjs_tk', 'no_bpjs_kes', 'no_rekening'], true)) {
                    $sheet->setCellValueExplicit($cellRef, $value !== null ? (string) $value : '—', DataType::TYPE_STRING);
                } elseif ($isMoney) {
                    // Kolom-kolom yang PAKAI FORMULA
                    if ($key === 'upah_penuh') {
                        $sheet->setCellValue($cellRef, "={$refGP}+{$refTT}");
                    } elseif ($key === 'kompensasi_pwt' && !$isMdRow) {
                        $sheet->setCellValue($cellRef, "=({$refGP}+{$refTT})/12");
                    } elseif ($key === 'potongan_jht') {
                        $sheet->setCellValue($cellRef, "={$refUP}*2%");
                    } elseif ($key === 'potongan_pensiun') {
                        $sheet->setCellValue($cellRef, "={$refUP}*1%");
                    } elseif ($key === 'potongan_kes') {
                        $sheet->setCellValue($cellRef, "={$refUP}*1%");
                    } elseif ($key === 'potongan_alpa') {
                        $sheet->setCellValue($cellRef, $formulaAlpa);
                    } elseif ($key === 'potongan_insentif') {
                        $sheet->setCellValue($cellRef, $formulaPotIns ?: 0);
                    } elseif ($key === 'gaji_kotor') {
                        $sheet->setCellValue($cellRef, $formulaGajiKotor);
                    } elseif ($key === 'gaji_bersih') {
                        $sheet->setCellValue($cellRef, $formulaGajiBersih);
                    } else {
                        // Kolom money biasa (Gaji Pokok, TTT, Upah Lembur, dll) — angka mati input HR
                        $sheet->setCellValue($cellRef, $value !== null && $value !== '' ? (float) $value : 0);
                    }
                    $this->rupiah($sheet, $cellRef);
                } else {
                    $sheet->setCellValue($cellRef, $value === null || $value === '' ? '—' : $value);
                }

                $this->cell($sheet, $cellRef, $bg, $key === 'gaji_bersih', $align);
                $c++;
            }
            $sheet->getRowDimension($r)->setRowHeight(15);
        }

        // Baris TOTAL
        $lastRow  = $startRow + count($rows) - 1;
        $totalRow = $lastRow + 1;
        if (count($rows) > 0) {
            $gajiPokokIdx = array_search('gaji_pokok', array_keys($cols), true);
            $mergeEndCol  = Coordinate::stringFromColumnIndex(max(1, $gajiPokokIdx));
            $sheet->mergeCells("A{$totalRow}:{$mergeEndCol}{$totalRow}");
            $sheet->setCellValue("A{$totalRow}", 'TOTAL');
            $sheet->getStyle("A{$totalRow}")->applyFromArray([
                'font' => ['name' => 'Arial', 'size' => 9, 'bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9D9D9']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);

            $c = 1;
            foreach (array_keys($cols) as $key) {
                $colL    = Coordinate::stringFromColumnIndex($c);
                $cellRef = $colL . $r;
                if (in_array($key, $moneyCols, true)) {
                    $cellRef = $colL . $totalRow;
                    $sheet->setCellValue($cellRef, "=SUM({$colL}{$startRow}:{$colL}{$lastRow})");
                    $this->rupiah($sheet, $cellRef);
                    $sheet->getStyle($cellRef)->applyFromArray([
                        'font' => ['name' => 'Arial', 'size' => 9, 'bold' => true],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9D9D9']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'AAAAAA']]],
                    ]);
                }
                $c++;
            }
            $sheet->getRowDimension($totalRow)->setRowHeight(18);
        }

        $lastTableRow = count($rows) > 0 ? $totalRow : $lastRow;
        $sheet->getStyle("A{$rGroup}:{$lastColLetter}{$lastTableRow}")->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
            ],
        ]);

        // ── Build sheet TIMESHEET (Tahap 3) ──
        $tsMap = $this->buildTimesheetSheet($wb, $rows, $tahun, $bulan, $namaBulan, $namaProj);

        // ── Build sheet SLIP GAJI (Tahap 4) — template interaktif dgn dropdown ──
        $this->buildSlipGajiSheet(
            $wb, $rows, $colLetter, $startRow, $lastRow, $tahun, $bulan, $namaBulan
        );

        // ── Update kolom di DATA GAJI supaya link ke sheet TIMESHEET ──
        foreach ($rows as $idx => $row) {
            $r = $startRow + $idx;
            $badge = $row['id_badge'] ?? null;
            if (!$badge || !isset($tsMap[$badge])) continue;
            $ts = $tsMap[$badge];

            $mapCols = [
                'izin'  => $ts['izin'],
                'sakit' => $ts['sakit'],
                'alpa'  => $ts['alpa'],
                'cuti'  => $ts['cuti'],
            ];
            foreach ($mapCols as $key => $tsRef) {
                if (isset($colLetter[$key])) {
                    $sheet->setCellValue($colLetter[$key] . $r, "=TIMESHEET!{$tsRef}");
                }
            }
        }

        // Auto-fit lebar kolom berdasarkan konten
        for ($i = 1; $i <= $n; $i++) {
            $colL = Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($colL)->setAutoSize(true);
        }

        // Set sheet DATA GAJI sebagai aktif saat file dibuka
        $wb->setActiveSheetIndex(0);

        // Print settings
        $sheet->freezePane('C' . $startRow);
        $sheet->setShowGridlines(false);
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A3);
        $sheet->getPageSetup()->setFitToPage(true);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(0.5)->setBottom(0.5)->setLeft(0.4)->setRight(0.4);

        $kodeProj = strtoupper($project?->kode ?? 'ALL');
        $filename = 'Data_Gaji_' . $kodeProj . '_' . strtoupper($namaBulan) . "_{$tahun}.xlsx";
        $filename = preg_replace('/[^A-Za-z0-9_\.]/', '', $filename);

        $writer = new Xlsx($wb);
        return response()->stream(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'max-age=0',
        ]);
    }

    private function bulanNamaList(): array
    {
        return [
            1 => 'Januari',  2 => 'Februari', 3 => 'Maret',    4 => 'April',
            5 => 'Mei',      6 => 'Juni',      7 => 'Juli',     8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
    }

    // ════════════════════════════════════════════════════════════
    // BUILD SHEET TIMESHEET
    // Returns: array of ['badge' => ['range' => 'D5:AH5', 'total_col' => 'AI5', ...]]
    // untuk keperluan formula link dari sheet DATA GAJI.
    // ════════════════════════════════════════════════════════════
    private function buildTimesheetSheet(
        Spreadsheet $wb,
        array $rows,
        int $tahun,
        int $bulan,
        string $namaBulan,
        string $namaProj
    ): array {
        $sheet = $wb->createSheet();
        $sheet->setTitle('TIMESHEET');

        $daysInMonth = Carbon::create($tahun, $bulan)->daysInMonth;
        $holidays    = Holiday::inMonth($tahun, $bulan)->get()->keyBy(fn($h) => (int) $h->tanggal->format('j'));

        // ── Judul ──
        $lastCol = Coordinate::stringFromColumnIndex(4 + $daysInMonth + 5);
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A1', 'TIMESHEET ' . strtoupper($namaBulan) . " {$tahun} — PROJECT " . strtoupper($namaProj));
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['name' => 'Arial', 'size' => 14, 'bold' => true],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF99']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(26);
        $sheet->getRowDimension(2)->setRowHeight(6);

        // ── Header 2 baris: baris 3 = "Hari", baris 4 = tanggal ──
        $rHari = 3;
        $rTgl  = 4;

        // Kolom fixed: A=No, B=Nama, C=Badge, D=Jabatan
        $fixed = [
            'A' => ['No.',           5],
            'B' => ['Nama Karyawan', 22],
            'C' => ['Badge',         12],
            'D' => ['Jabatan',       15],
        ];
        foreach ($fixed as $col => [$label, $width]) {
            $sheet->setCellValue("{$col}{$rHari}", $label);
            $sheet->mergeCells("{$col}{$rHari}:{$col}{$rTgl}");
            $sheet->getStyle("{$col}{$rHari}")->applyFromArray([
                'font'      => ['name' => 'Arial', 'size' => 9, 'bold' => true],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF99']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
            ]);
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        // Kolom tanggal 1..daysInMonth (mulai kolom E)
        $hariNama = ['Min','Sen','Sel','Rab','Kam','Jum','Sab'];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $colIdx = 4 + $d; // E = 5
            $col = Coordinate::stringFromColumnIndex($colIdx);
            $date = Carbon::create($tahun, $bulan, $d);
            $isSun = $date->isSunday();
            $isSat = $date->isSaturday();
            $isHol = isset($holidays[$d]);

            $bg = 'FFFF99';
            if ($isSun)      $bg = self::C_TS_SUN;
            elseif ($isHol)  $bg = self::C_TS_HOL;
            elseif ($isSat)  $bg = self::C_TS_SAT;

            $sheet->setCellValue("{$col}{$rHari}", $hariNama[$date->dayOfWeek]);
            $sheet->setCellValue("{$col}{$rTgl}",  $d);
            foreach ([$rHari, $rTgl] as $rr) {
                $sheet->getStyle("{$col}{$rr}")->applyFromArray([
                    'font'      => ['name' => 'Arial', 'size' => 8, 'bold' => true],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
                ]);
            }
            $sheet->getColumnDimension($col)->setWidth(4);
        }

        // Kolom summary: Total Jam | Izin | Sakit | Alpa | Cuti
        $summaryLabels = ['Total Jam', 'Izin', 'Sakit', 'Alpa', 'Cuti'];
        $sumStartIdx   = 4 + $daysInMonth + 1;
        foreach ($summaryLabels as $i => $label) {
            $col = Coordinate::stringFromColumnIndex($sumStartIdx + $i);
            $sheet->setCellValue("{$col}{$rHari}", $label);
            $sheet->mergeCells("{$col}{$rHari}:{$col}{$rTgl}");
            $sheet->getStyle("{$col}{$rHari}")->applyFromArray([
                'font'      => ['name' => 'Arial', 'size' => 9, 'bold' => true],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF99']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
            ]);
            $sheet->getColumnDimension($col)->setWidth(7);
        }

        $sheet->getRowDimension($rHari)->setRowHeight(14);
        $sheet->getRowDimension($rTgl)->setRowHeight(14);

        // ── Data rows ──
        $tsMap = []; // ['badge' => ['range' => 'E5:AI5', 'total_jam' => 'AJ5', 'izin' => 'AK5', ...]]
        $startDataRow = $rTgl + 1;

        // Ambil semua data timesheet bulan ini sekaligus (hindari query per-baris di dalam loop)
        $employeeIds = collect($rows)->pluck('employee_id')->filter()->unique()->values()->all();
        $timesheetsByEmployee = empty($employeeIds) ? collect() : Timesheet::whereIn('employee_id', $employeeIds)
            ->where('tahun', $tahun)
            ->where('bulan', $bulan)
            ->get()
            ->groupBy('employee_id');

        foreach ($rows as $idx => $row) {
            $r = $startDataRow + $idx;
            $empId = $row['employee_id'] ?? null;

            // Kolom fixed
            $sheet->setCellValue("A{$r}", $idx + 1);
            $sheet->setCellValue("B{$r}", strtoupper($row['nama_lengkap'] ?? '—'));
            $sheet->setCellValueExplicit("C{$r}", (string)($row['id_badge'] ?? '—'), DataType::TYPE_STRING);
            $sheet->setCellValue("D{$r}", $row['jabatan'] ?? '—');

            foreach (['A','B','C','D'] as $col) {
                $sheet->getStyle("{$col}{$r}")->applyFromArray([
                    'font'      => ['name' => 'Arial', 'size' => 9],
                    'alignment' => ['horizontal' => $col === 'A' || $col === 'C' ? Alignment::HORIZONTAL_CENTER : Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
                ]);
            }

            // Ambil data timesheet dari peta yang sudah di-load di awal (bukan query per-baris)
            $tsData = $empId && isset($timesheetsByEmployee[$empId])
                ? $timesheetsByEmployee[$empId]->pluck('nilai', 'hari')->toArray()
                : [];

            // Isi sel per hari
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $colIdx = 4 + $d;
                $col = Coordinate::stringFromColumnIndex($colIdx);
                $val = $tsData[$d] ?? null;
                $date = Carbon::create($tahun, $bulan, $d);
                $isSun = $date->isSunday();
                $isSat = $date->isSaturday();
                $isHol = isset($holidays[$d]);

                // Warna background hari
                $bg = 'FFFFFF';
                if ($isSun) $bg = self::C_TS_SUN;
                elseif ($isHol) $bg = self::C_TS_HOL;
                elseif ($isSat) $bg = self::C_TS_SAT;

                // Value & style status
                if ($val !== null && $val !== '') {
                    $up = strtoupper((string)$val);
                    if (in_array($up, ['I','S','A','C','STB'], true)) {
                        $sheet->setCellValueExplicit("{$col}{$r}", $up, DataType::TYPE_STRING);
                        $bg = match ($up) {
                            'I' => self::C_TS_IZIN,
                            'S' => self::C_TS_SAKIT,
                            'A' => self::C_TS_ALPA,
                            'C' => self::C_TS_CUTI,
                            'STB' => self::C_TS_STB,
                        };
                    } elseif (is_numeric($val)) {
                        $sheet->setCellValue("{$col}{$r}", (float) $val);
                        if ((float) $val >= 10) $bg = self::C_TS_HADIR;
                    } else {
                        $sheet->setCellValue("{$col}{$r}", $val);
                    }
                }

                $sheet->getStyle("{$col}{$r}")->applyFromArray([
                    'font'      => ['name' => 'Arial', 'size' => 9, 'bold' => in_array(strtoupper((string)($val ?? '')), ['I','S','A','C','STB'], true)],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
                ]);
            }

            // Kolom summary — pakai formula
            $firstDayCol = Coordinate::stringFromColumnIndex(5);
            $lastDayCol  = Coordinate::stringFromColumnIndex(4 + $daysInMonth);
            $range = "{$firstDayCol}{$r}:{$lastDayCol}{$r}";

            $summaryCols = [];
            foreach ($summaryLabels as $i => $label) {
                $col = Coordinate::stringFromColumnIndex($sumStartIdx + $i);
                $summaryCols[$label] = "{$col}{$r}";
                if ($label === 'Total Jam') {
                    $sheet->setCellValue("{$col}{$r}", "=SUM({$range})");
                } else {
                    $code = strtoupper($label[0]); // I, S, A, C
                    $sheet->setCellValue("{$col}{$r}", "=COUNTIF({$range},\"{$code}\")");
                }
                $sheet->getStyle("{$col}{$r}")->applyFromArray([
                    'font'      => ['name' => 'Arial', 'size' => 9, 'bold' => true],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
                ]);
            }

            if ($row['id_badge']) {
                $tsMap[$row['id_badge']] = [
                    'row'       => $r,
                    'total_jam' => $summaryCols['Total Jam'],
                    'izin'      => $summaryCols['Izin'],
                    'sakit'     => $summaryCols['Sakit'],
                    'alpa'      => $summaryCols['Alpa'],
                    'cuti'      => $summaryCols['Cuti'],
                ];
            }

            $sheet->getRowDimension($r)->setRowHeight(15);
        }

        // Freeze pane di kolom E (setelah D=Jabatan), baris data pertama
        $sheet->freezePane('E' . $startDataRow);
        $sheet->setShowGridlines(false);
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A3);
        $sheet->getPageSetup()->setFitToPage(true);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);

        return $tsMap;
    }


    private function buildSlipGajiSheet(
        Spreadsheet $wb,
        array $rows,
        array $colLetter,
        int $startDgRow,
        int $lastDgRow,
        int $tahun,
        int $bulan,
        string $namaBulan
    ): void {
        $sheet = $wb->createSheet();
        $sheet->setTitle('SLIP GAJI');

        // Range DATA GAJI: dari kolom pertama (No) sampai kolom terakhir
        $firstDgCol = 'A';
        $lastDgCol  = end($colLetter);
        $dgRange    = "'DATA GAJI'!\${$firstDgCol}\${$startDgRow}:\${$lastDgCol}\${$lastDgRow}";
        $jumlahKaryawan = count($rows);

        // Baris spinner akan di-set setelah header dibuat (lihat blok "SPINNER" di bawah)
        $spinnerRow = null;

        // Helper: buat formula INDEX — pakai referensi ke sel spinner
        $idx = function (string $key) use ($colLetter, $dgRange, &$spinnerRow) {
            if (!isset($colLetter[$key])) return '""';
            $targetColIdx = Coordinate::columnIndexFromString($colLetter[$key]);
            return "=IFERROR(INDEX({$dgRange},\$B\${$spinnerRow},{$targetColIdx}),\"\")";
        };

        // Lebar kolom
        $sheet->getColumnDimension('A')->setWidth(18);
        $sheet->getColumnDimension('B')->setWidth(4);
        $sheet->getColumnDimension('C')->setWidth(28);
        $sheet->getColumnDimension('D')->setWidth(3);
        $sheet->getColumnDimension('E')->setWidth(16);
        $sheet->getColumnDimension('F')->setWidth(3);
        $sheet->getColumnDimension('G')->setWidth(18);

        $borderMed = ['borders' => [
            'top'    => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '000000']],
            'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '000000']],
        ]];

        $r = 1;

        // ── Header perusahaan ──
        // Logo di kolom A (merge A1:A3)
        $rLogo = $r;
        $sheet->mergeCells("A{$rLogo}:A" . ($rLogo + 2));
        $logoPath = public_path('logo-akm.png');
        if (file_exists($logoPath)) {
            $drawing = new Drawing();
            $drawing->setName('Logo AKM');
            $drawing->setPath($logoPath);
            $drawing->setHeight(70);
            $drawing->setCoordinates("A{$rLogo}");
            $drawing->setOffsetX(8);
            $drawing->setOffsetY(4);
            $drawing->setWorksheet($sheet);
        } else {
            $sheet->setCellValue("A{$rLogo}", '[ LOGO ]');
            $sheet->getStyle("A{$rLogo}")->applyFromArray([
                'font' => ['size' => 9, 'name' => 'Tahoma', 'italic' => true, 'color' => ['rgb' => '999999']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
        }

        // Baris 1: Nama perusahaan (B..G merge)
        $sheet->mergeCells("B{$r}:G{$r}");
        $sheet->setCellValue("B{$r}", 'PT. ANDALAS KARYA MULIA');
        $sheet->getStyle("B{$r}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'name' => 'Tahoma', 'color' => ['rgb' => '000000']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($r)->setRowHeight(40);
        $r++;

        // Baris 2: Alamat baris 1
        $sheet->mergeCells("B{$r}:G{$r}");
        $sheet->setCellValue("B{$r}", 'Jl. Wonosari, Komplek Wonosari Regency Blok B No. 1 Tangkerang Selatan - Pekanbaru');
        $sheet->getStyle("B{$r}")->applyFromArray([
            'font' => ['size' => 9, 'name' => 'Tahoma', 'color' => ['rgb' => '000000']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($r)->setRowHeight(14);
        $r++;

        // Baris 3: Alamat baris 2 (telp/fax/web)
        $sheet->mergeCells("B{$r}:G{$r}");
        $sheet->setCellValue("B{$r}", 'Telp. 0761-39213  Fax. 0761-39213 ; Web : http://www.andalaskarya.com');
        $sheet->getStyle("B{$r}")->applyFromArray([
            'font' => ['size' => 9, 'name' => 'Tahoma', 'color' => ['rgb' => '000000']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($r)->setRowHeight(14);
        $r++;

        $sheet->mergeCells("A{$r}:D{$r}");
        $sheet->setCellValue("A{$r}", 'SLIP GAJI KARYAWAN');
        $sheet->getStyle("A{$r}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'name' => 'Arial'],
        ]);
        $sheet->mergeCells("E{$r}:G{$r}");
        $sheet->setCellValue("E{$r}", "Periode : {$namaBulan} {$tahun}");
        $sheet->getStyle("E{$r}")->applyFromArray([
            'font' => ['size' => 10, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
        ]);
        $sheet->getStyle("A{$r}:G{$r}")->applyFromArray($borderMed);
        $sheet->getRowDimension($r)->setRowHeight(20);
        $r++;
        $r++;

        // ── SPINNER PILIH KARYAWAN ──
        $spinnerRow = $r; // simpan baris spinner untuk formula INDEX
        $sheet->setCellValue("A{$r}", 'No. Urut Karyawan:');
        $sheet->getStyle("A{$r}")->applyFromArray([
            'font' => ['size' => 9, 'name' => 'Arial', 'color' => ['rgb' => '000000']],
        ]);

        // Sel spinner — default 1, tampilan minimal
        $sheet->setCellValue("B{$r}", 1);
        $sheet->getStyle("B{$r}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'name' => 'Arial', 'color' => ['rgb' => '000000']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '999999']]],
            'numberFormat' => ['formatCode' => '0'],
        ]);

        // Data Validation type WHOLE — Excel akan tampilkan panah scroll otomatis
        $validation = $sheet->getCell("B{$r}")->getDataValidation();
        $validation->setType(DataValidation::TYPE_WHOLE);
        $validation->setOperator(DataValidation::OPERATOR_BETWEEN);
        $validation->setFormula1('1');
        $validation->setFormula2((string) $jumlahKaryawan);
        $validation->setAllowBlank(false);
        $validation->setShowInputMessage(true);
        $validation->setShowErrorMessage(true);
        $validation->setPromptTitle('Pilih Karyawan');
        $validation->setPrompt("Ketik angka 1 s/d {$jumlahKaryawan} untuk pilih karyawan.\nAtau pakai panah ↑↓ di keyboard setelah klik sel.");
        $validation->setErrorTitle('Nomor tidak valid');
        $validation->setError("Nomor harus antara 1 dan {$jumlahKaryawan}");

        // Info dari - sampai
        $sheet->mergeCells("C{$r}:D{$r}");
        $sheet->setCellValue("C{$r}", "dari {$jumlahKaryawan} karyawan");
        $sheet->getStyle("C{$r}")->applyFromArray([
            'font' => ['size' => 10, 'name' => 'Arial', 'color' => ['rgb' => '666666']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // Tampilkan nama karyawan aktif di kanan
        $sheet->mergeCells("E{$r}:G{$r}");
        $sheet->setCellValue("E{$r}", $idx('nama_lengkap'));
        $sheet->getStyle("E{$r}")->applyFromArray([
            'font' => ['size' => 9, 'name' => 'Arial', 'italic' => true, 'color' => ['rgb' => '666666']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($r)->setRowHeight(18);
        $r++;
        $r++;

        // ── Identitas Karyawan (semua INDEX) ──
        $identitas = [
            ['No. Register',   $idx('id_badge')],
            ['Nama Karyawan',  $idx('nama_lengkap')],
            ['Jabatan',        $idx('jabatan')],
            ['No. Rekening',   $idx('no_rekening')],
            ['Nama Bank',      $idx('nama_bank')],
            ['No. BPJS TK',    $idx('no_bpjs_tk')],
            ['No. BPJS Kes',   $idx('no_bpjs_kes')],
            ['PTKP',           $idx('ptkp')],
        ];
        $rowNama       = $r + 1;
        $rowGajiBersih = $r + 2;

        foreach ($identitas as [$lbl, $formula]) {
            $sheet->setCellValue("A{$r}", $lbl);
            $sheet->setCellValue("B{$r}", ':');
            $sheet->mergeCells("C{$r}:D{$r}");
            $sheet->setCellValue("C{$r}", $formula);
            $sheet->getStyle("A{$r}")->applyFromArray([
                'font' => ['size' => 9, 'name' => 'Arial'],
            ]);
            $sheet->getStyle("C{$r}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 9, 'name' => 'Arial'],
            ]);
            $sheet->getRowDimension($r)->setRowHeight(15);
            $r++;
        }

        $sheet->mergeCells("E{$rowNama}:G{$rowNama}");
        $sheet->setCellValue("E{$rowNama}", 'GAJI BERSIH (NETTO)');
        $sheet->getStyle("E{$rowNama}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 9, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF99']],
        ]);

        $sheet->mergeCells("E{$rowGajiBersih}:G{$rowGajiBersih}");
        $sheet->setCellValue("E{$rowGajiBersih}", $idx('gaji_bersih'));
        $sheet->getStyle("E{$rowGajiBersih}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'name' => 'Arial', 'color' => ['rgb' => '006600']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']],
            'numberFormat' => ['formatCode' => '"Rp"#,##0'],
        ]);

        // All-border penuh untuk seluruh kotak Netto (baris label + angka)
        $sheet->getStyle("E{$rowNama}:G{$rowGajiBersih}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '000000']]],
        ]);
        $sheet->getRowDimension($rowGajiBersih)->setRowHeight(32);
        $r++;

        // ── Helper untuk section ──

        $sectionRanges = []; // untuk apply border akhir per section

        $addSectionHeader = function (string $letter, string $title) use ($sheet, &$r) {
            $sheet->mergeCells("A{$r}:G{$r}");
            $sheet->setCellValue("A{$r}", ($letter ? $letter . '. ' : '') . $title);
            $sheet->getStyle("A{$r}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 10, 'name' => 'Arial'],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF99']],
            ]);
            $sheet->getRowDimension($r)->setRowHeight(18);
            $startR = $r;
            $r++;
            return $startR;
        };

        $addItem = function (string $no, string $nama, string $formula) use ($sheet, &$r) {
            $sheet->setCellValue("A{$r}", $no);
            $sheet->mergeCells("C{$r}:D{$r}");
            $sheet->setCellValue("C{$r}", $nama);
            $sheet->setCellValue("F{$r}", '=');
            $sheet->setCellValue("G{$r}", $formula);
            $sheet->getStyle("A{$r}:G{$r}")->getFont()->setSize(9)->setName('Arial');
            $sheet->getStyle("G{$r}")->getNumberFormat()->setFormatCode('"Rp"#,##0;("Rp"#,##0);"-"');
            $sheet->getStyle("G{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getRowDimension($r)->setRowHeight(15);
            $r++;
        };

        $addTotal = function (string $label, string $formula, bool $bold = false) use ($sheet, &$r, $borderMed) {
            $sheet->mergeCells("A{$r}:F{$r}");
            $sheet->setCellValue("A{$r}", $label);
            $sheet->setCellValue("G{$r}", $formula);
            $sheet->getStyle("A{$r}:G{$r}")->applyFromArray([
                'font' => ['bold' => $bold, 'size' => 10, 'name' => 'Arial'],
            ]);
            $sheet->getStyle("G{$r}")->getNumberFormat()->setFormatCode('"Rp"#,##0');
            $sheet->getStyle("G{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("A{$r}:G{$r}")->applyFromArray($borderMed);
            $sheet->getRowDimension($r)->setRowHeight(18);
            $r++;
        };

        // ── A. PEROLEHAN ──
        $sA = $addSectionHeader('A', 'PEROLEHAN');
        $addItem('1.', 'Gaji Pokok / Upah', $idx('gaji_pokok'));
        $addItem('2.', 'Tunjangan Tetap',   $idx('tunj_tetap'));
        $addItem('3.', 'Kompensasi PWT',    $idx('kompensasi_pwt'));
        $sectionRanges[] = ["A{$sA}", "G" . ($r - 1)];

        // ── B. TUNJANGAN TIDAK TETAP ──
        $sB = $addSectionHeader('B', 'TUNJANGAN TIDAK TETAP');
        $tttFields = [
            'tunj_makan'         => 'Uang Makan',
            'tunj_produksi'      => 'Tunj. Produksi',
            'tunj_lapangan'      => 'Tunj. Lapangan',
            'tunj_kehadiran'     => 'Tunj. Kehadiran',
            'tunj_pulsa'         => 'Tunj. Pulsa',
            'kompensasi_kontrak' => 'Komp. Kontrak',
            'insentif'           => 'Insentif',
            'com_day'            => 'Come Day',
        ];
        $tttNo = 1;
        foreach ($tttFields as $key => $label) {
            if (isset($colLetter[$key])) {
                $addItem($tttNo . '.', $label, $idx($key));
                $tttNo++;
            }
        }

        $sectionRanges[] = ["A{$sB}", "G" . ($r - 1)];
        // ── C. LEMBUR ──
        $sC = $addSectionHeader('C', 'LEMBUR');
        $addItem('1.', 'Upah Lembur', $idx('upah_lembur'));
        $sectionRanges[] = ["A{$sC}", "G" . ($r - 1)];

        $addTotal('GAJI SEBULAN (KOTOR)', $idx('gaji_kotor'), true);

        // ── D. POTONGAN ──
        $sD = $addSectionHeader('D', 'POTONGAN WAJIB');
        $addItem('1.', 'BPJS TK - JHT (2%)',     $idx('potongan_jht'));
        $addItem('2.', 'BPJS TK - Pensiun (1%)', $idx('potongan_pensiun'));
        $addItem('3.', 'BPJS Kesehatan (1%)',    $idx('potongan_kes'));
        $addItem('4.', 'Alpa / Pinjaman',        $idx('potongan_alpa'));
        if (isset($colLetter['potongan_insentif'])) {
            $addItem('5.', 'Potongan Insentif',   $idx('potongan_insentif'));
        }
        if (isset($colLetter['pot_tabung_oksigen'])) {
            $addItem('6.', 'Pot. Tabung Oksigen', $idx('pot_tabung_oksigen'));
        }
        $sectionRanges[] = ["A{$sD}", "G" . ($r - 1)];

        // Terapkan border luar (bingkai) ke tiap section — isi di dalam tetap polos
        foreach ($sectionRanges as [$topLeft, $bottomRight]) {
            $sheet->getStyle("{$topLeft}:{$bottomRight}")->applyFromArray([
                'borders' => ['outline' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
            ]);
        }

        $r++;

        // ── PENGHASILAN NETTO ──
        $sheet->mergeCells("A{$r}:F{$r}");
        $sheet->setCellValue("A{$r}", 'PENGHASILAN BERSIH (NETTO)');
        $sheet->setCellValue("G{$r}", $idx('gaji_bersih'));
        $sheet->getStyle("A{$r}:G{$r}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'name' => 'Arial'],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF99']],
            'borders' => [
                'top'    => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '000000']],
                'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '000000']],
            ],
        ]);
        $sheet->getStyle("G{$r}")->getNumberFormat()->setFormatCode('"Rp"#,##0');
        $sheet->getStyle("G{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getRowDimension($r)->setRowHeight(50);
        $r += 3;

        // ── Tanggal ──
        $sheet->mergeCells("A{$r}:G{$r}");
        $sheet->setCellValue("A{$r}", 'Pekanbaru, ' . now()->isoFormat('D MMMM Y'));
        $sheet->getStyle("A{$r}")->applyFromArray([
            'font' => ['size' => 9, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
        ]);
        $r += 2;

        // ── TTD 3 kolom ──
        $ttdRow = $r;
        foreach (['A' => 'Disetujui Oleh,', 'C' => 'Dibayar Oleh,', 'E' => 'Diterima Oleh,'] as $col => $label) {
            $sheet->setCellValue("{$col}{$ttdRow}", $label);
            $sheet->getStyle("{$col}{$ttdRow}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 9, 'name' => 'Arial'],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
        }
        $r += 4;
        foreach (['A' => 'H. Syahrul Akmal', 'C' => 'Yulhamdani', 'E' => $idx('nama_lengkap')] as $col => $name) {
            $sheet->setCellValue("{$col}{$r}", $name);
            $sheet->getStyle("{$col}{$r}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 9, 'name' => 'Arial'],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'borders' => ['top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
            ]);
        }
        $r++;
        foreach (['A' => 'Direktur Utama', 'C' => 'Finance', 'E' => $idx('jabatan')] as $col => $val) {
            $sheet->setCellValue("{$col}{$r}", $val);
            $sheet->getStyle("{$col}{$r}")->applyFromArray([
                'font' => ['italic' => true, 'size' => 9, 'name' => 'Arial'],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
        }

        // Print & display settings
        $sheet->setShowGridlines(false);
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_PORTRAIT);
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);
        $sheet->getPageSetup()->setFitToPage(true);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(1);
        $sheet->getPageMargins()->setTop(0.6)->setBottom(0.6)->setLeft(0.7)->setRight(0.5);
    }
}