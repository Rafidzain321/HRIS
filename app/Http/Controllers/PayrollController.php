<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\EmployeePayroll;
use App\Models\Holiday;
use App\Models\Project;
use App\Models\Timesheet;
use App\Models\TimesheetMember;
use App\Models\ProjectTttItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class PayrollController extends Controller
{
    const TARIF_SABTU = 75000;
    const TARIF_LIBUR = 200000;
    const TARIF_BIASA = 20000;

    // ════════════════════════════════════════════════════════════
    // SLIP GAJI — tampilan per karyawan
    // ════════════════════════════════════════════════════════════
    public function slipGaji(Request $request)
    {
        $tahun      = (int) $request->get('tahun', now()->year);
        $bulan      = (int) $request->get('bulan', now()->month);
        $employeeId = $request->get('employee_id');
        $bulanNama  = $this->bulanNama();
        $projectId  = $this->activeProjectId();

        $members = TimesheetMember::where('aktif', true)
            ->when($projectId, fn($q, $pid) => $q->where('project_id', $pid))
            ->with('employee.position')
            ->orderBy('urutan')->orderBy('id_badge')
            ->get();

        $employeeList = $members->map(fn($m) => [
            'id'           => $m->employee?->id,
            'id_badge'     => $m->id_badge,
            'nama_lengkap' => $m->nama_override ?? $m->employee?->nama_lengkap ?? '—',
            'jabatan'      => $m->employee?->position?->nama_jabatan ?? '—',
            'tipe'         => $m->tipe ?? '7jam',
        ])->filter(fn($e) => $e['id'])->values();

        $slipData    = null;
        $employee    = null;
        $hariDetails = [];

        if ($employeeId) {
            $employee = Employee::with(['position', 'project'])->find($employeeId);

            if ($employee) {
                $isMd = $employee->project?->tipe_gaji === 'md';

                // Ambil kelompok dari TimesheetMember
                $member  = $members->firstWhere('id_badge', $employee->id_badge);
                $isFlat  = $member?->kelompok === 'flat';

                if ($isMd) {
                    $tsData   = $this->getTimesheetData($employee->id, $tahun, $bulan);
                    $slipData = $this->hitungSlipGajiMd($employee, $tahun, $bulan, $tsData);
                } else {
                    $slipData = $this->hitungSlipGaji($employee, $tahun, $bulan, $isFlat);
                }

                $hariDetails  = $slipData['hari_details'];
                $savedPayroll = EmployeePayroll::where([
                    'employee_id' => $employee->id,
                    'tahun'       => $tahun,
                    'bulan'       => $bulan,
                ])->first();

                if ($savedPayroll && $slipData) {
                    // ── Override field-field dari data tersimpan (single source of truth) ──
                    $slipData['tunj_makan']            = $savedPayroll->tunj_makan            ?? $slipData['tunj_makan'];
                    $slipData['tunj_produksi']         = $savedPayroll->tunj_produksi         ?? $slipData['tunj_produksi'];
                    $slipData['tunj_lapangan']         = $savedPayroll->tunj_lapangan         ?? $slipData['tunj_lapangan'];
                    $slipData['tunj_kehadiran']        = $savedPayroll->tunj_kehadiran        ?? 0;
                    $slipData['tunj_pulsa']            = $savedPayroll->tunj_pulsa            ?? 0;
                    $slipData['kompensasi_kontrak']    = $savedPayroll->kompensasi_kontrak    ?? 0;
                    $slipData['insentif']              = $savedPayroll->insentif              ?? $slipData['insentif'];
                    $slipData['com_day']               = $savedPayroll->com_day               ?? $slipData['com_day'];
                    $slipData['gaji_pokok']            = $savedPayroll->gaji_pokok !== null ? $savedPayroll->gaji_pokok : $slipData['gaji_pokok'];
                    $slipData['tunj_tetap']            = $savedPayroll->tunj_tetap !== null ? $savedPayroll->tunj_tetap : $slipData['tunj_tetap'];

                    // Field tambahan yg sebelumnya missing — inilah root cause slip UI salah
                    $slipData['kompensasi_pwt']        = $savedPayroll->kompensasi_pwt        ?? $slipData['kompensasi_pwt'];
                    $slipData['upah_lembur']           = $savedPayroll->upah_lembur           ?? $slipData['upah_lembur'];
                    $slipData['total_lembur_flat']     = $savedPayroll->total_lembur_flat     ?? $slipData['total_lembur_flat'];
                    $slipData['jml_jam_lembur']        = $savedPayroll->jml_jam_lembur        ?? $slipData['jml_jam_lembur'];
                    $slipData['l_sabtu']               = $savedPayroll->l_sabtu               ?? $slipData['l_sabtu'];
                    $slipData['l_libur']               = $savedPayroll->l_libur               ?? $slipData['l_libur'];
                    $slipData['lembur_biasa']          = $savedPayroll->lembur_biasa          ?? $slipData['lembur_biasa'];
                    $slipData['uang_hadir']            = $savedPayroll->uang_hadir            ?? $slipData['uang_hadir'];
                    $slipData['potongan_alpa']         = $savedPayroll->potongan_alpa         ?? $slipData['potongan_alpa'];
                    $slipData['potongan_insentif']     = $savedPayroll->potongan_insentif     ?? 0;
                    $slipData['pot_tabung_oksigen']    = $savedPayroll->pot_tabung_oksigen    ?? 0;
                    $slipData['kekurangan_bulan_lalu'] = $savedPayroll->kekurangan_bulan_lalu ?? 0;
                    $slipData['stb']                   = $savedPayroll->stb                   ?? $slipData['stb'] ?? 0;

                    if ($isMd) {
                        $slipData['ttt_perhari'] = $savedPayroll->ttt_perhari ?? $slipData['ttt_perhari'];
                        $slipData['h_kerja']     = $savedPayroll->h_kerja     ?? $slipData['h_kerja'];
                        $slipData['h_basic']     = $savedPayroll->h_basic     ?? $slipData['h_basic'];
                        $slipData['h_sabtu']     = $savedPayroll->h_sabtu     ?? $slipData['h_sabtu'];
                    }

                    // ── Recalc gaji_kotor & gaji_bersih (respect % BPJS live) ──
                    $upahPenuh = $slipData['gaji_pokok'] + $slipData['tunj_tetap'];

                    if (!$isMd) {
                        $tttSum = $slipData['tunj_makan'] + $slipData['tunj_produksi'] + $slipData['tunj_lapangan']
                                + $slipData['tunj_kehadiran'] + $slipData['tunj_pulsa'] + $slipData['kompensasi_kontrak']
                                + $slipData['insentif'] + $slipData['com_day'];

                        // Gunakan upah_lembur (per_jam) atau total_lembur_flat (flat)
                        // Pakai `?:` supaya 0 fallback (bukan `??` yang hanya null-safe)
                        $upahLembur = ($slipData['kelompok'] ?? '') === 'flat'
                            ? ($slipData['total_lembur_flat'] ?: 0)
                            : ($slipData['upah_lembur'] ?: 0);

                        $slipData['gaji_kotor'] = round(
                            $slipData['gaji_pokok']
                            + $slipData['tunj_tetap']
                            + $slipData['kompensasi_pwt']
                            + $tttSum
                            + $upahLembur
                        );

                        $slipData['gaji_bersih'] = round(
                            $slipData['gaji_kotor']
                            - round($upahPenuh * 0.02)  // JHT default 2%
                            - round($upahPenuh * 0.01)  // Pensiun 1%
                            - round($upahPenuh * 0.01)  // Kesehatan 1%
                            - ($slipData['potongan_alpa']     ?? 0)
                            - ($slipData['potongan_insentif'] ?? 0)
                            - ($slipData['pot_tabung_oksigen'] ?? 0)
                            + ($slipData['kekurangan_bulan_lalu'] ?? 0)
                        );
                    } else {
                        // MD calc — sama seperti sebelumnya
                        $hKerja      = $slipData['h_basic'] ?? 0;
                        $hSabtu      = $slipData['h_sabtu'] ?? 0;
                        $uBasic      = round($upahPenuh / 17 * min($hKerja, 17), 2);
                        $uKerja      = round((($slipData['tunj_makan'] ?? 0) + ($slipData['tunj_kehadiran'] ?? 0)) * ($slipData['h_kerja'] ?? 0), 2);
                        $comDayTotal = round(($slipData['com_day'] ?? 0) * $hSabtu, 2);
                        $upahLembur  = $savedPayroll->upah_lembur ?? $slipData['upah_lembur'] ?? 0;

                        $slipData['u_basic'] = $uBasic;
                        $slipData['u_kerja'] = $uKerja;
                        $slipData['upah_lembur'] = $upahLembur;

                        $slipData['gaji_kotor'] = round(
                            $uBasic
                            + ($slipData['kompensasi_pwt'] ?? 0)
                            + $uKerja
                            + $comDayTotal
                            + $upahLembur
                            + ($slipData['tunj_pulsa'] ?? 0)
                            + ($slipData['kekurangan_bulan_lalu'] ?? 0),
                            2
                        );
                        $slipData['gaji_bersih'] = round(
                            $slipData['gaji_kotor']
                            - round($upahPenuh * 0.02)
                            - round($upahPenuh * 0.01)
                            - round($upahPenuh * 0.01)
                            - ($slipData['potongan_alpa'] ?? 0),
                            2
                        );
                    }
                }
            }
        }

        return Inertia::render('Timesheet/SlipGaji', [
            'tahun'         => $tahun,
            'bulan'         => $bulan,
            'bulan_nama'    => $bulanNama[$bulan],
            'bulan_list'    => $bulanNama,
            'employee_id'   => $employeeId ? (int) $employeeId : null,
            'employee_list' => $employeeList,
            'project_id'    => $projectId,
            'slip'          => $slipData ? array_merge($slipData, [
                'hari_details' => null,
                'no_rekening'  => $employee?->no_rekening ?? null,
            ]) : null,
            'hari_details'  => $hariDetails,
        ]);
    }


    // ════════════════════════════════════════════════════════════
    // DATA GAJI — rekap semua karyawan
    // ════════════════════════════════════════════════════════════
    public function dataGaji(Request $request)
    {
        $tahun     = (int) $request->get('tahun', now()->year);
        $bulan     = (int) $request->get('bulan', now()->month);
        $bulanNama = $this->bulanNama();
        $projectId = $this->activeProjectId();

        $built = $this->buildPayrollRows($tahun, $bulan, $projectId);

        return Inertia::render('Timesheet/DataGaji', [
            'tahun'             => $tahun,
            'bulan'             => $bulan,
            'bulan_nama'        => $bulanNama[$bulan],
            'bulan_list'        => $bulanNama,
            'rows'              => $built['rows'],
            'total_gaji_kotor'  => $built['total_gaji_kotor'],
            'total_gaji_bersih' => $built['total_gaji_bersih'],
            'ttt_items'         => $built['ttt_items'],
            'project_info'      => $projectId
                ? Project::find($projectId, ['id', 'kode', 'nama', 'tipe_gaji', 'tipe_timesheet'])
                : null,
        ]);
    }


    // ════════════════════════════════════════════════════════════
    // BUILD ROWS — dipakai bersama oleh dataGaji() dan export Excel
    // ════════════════════════════════════════════════════════════
    public function buildPayrollRows(int $tahun, int $bulan, ?int $projectId): array
    {
        $members = TimesheetMember::where('aktif', true)
            ->when($projectId, fn($q, $pid) => $q->where('project_id', $pid))
            ->with('employee.position', 'employee.project')
            ->orderBy('urutan')->orderBy('id_badge')
            ->get();

        // Ambil semua EmployeePayroll tersimpan untuk periode ini dalam 1 query
        // (dipakai di loop utama & loop custom TTT di bawah — sebelumnya query per-karyawan berulang).
        $employeeIds = [];
        foreach ($members as $m) {
            if ($m->employee) {
                $employeeIds[] = $m->employee->id;
            }
        }
        $savedPayrolls = EmployeePayroll::where('tahun', $tahun)
            ->where('bulan', $bulan)
            ->whereIn('employee_id', $employeeIds)
            ->get()
            ->keyBy('employee_id');

        $rows            = [];
        $totalGajiKotor  = 0;
        $totalGajiBersih = 0;

        foreach ($members as $m) {
            $emp = $m->employee;
            if (!$emp) continue;

            $isMd   = $emp->project?->tipe_gaji === 'md';
            $isFlat = $m->kelompok === 'flat';

            $saved = $savedPayrolls->get($emp->id);

            if ($isMd) {
                $tsData = $this->getTimesheetData($emp->id, $tahun, $bulan);
                $slip   = $this->hitungSlipGajiMd($emp, $tahun, $bulan, $tsData);
            } else {
                $slip = $this->hitungSlipGaji($emp, $tahun, $bulan, $isFlat);
            }

            if ($saved) {
                $slip['gaji_pokok']   = $saved->gaji_pokok !== null ? $saved->gaji_pokok : $slip['gaji_pokok'];
                $slip['tunj_tetap']   = $saved->tunj_tetap !== null ? $saved->tunj_tetap : $slip['tunj_tetap'];
                $slip['tunj_makan']         = $saved->tunj_makan;
                $slip['tunj_produksi']      = $saved->tunj_produksi;
                $slip['tunj_lapangan']      = $saved->tunj_lapangan;
                $slip['uang_hadir']         = $saved->uang_hadir;
                $slip['lembur_biasa']       = $saved->lembur_biasa;
                $slip['total_lembur_flat']  = $saved->total_lembur_flat ?? $slip['total_lembur_flat'];
                $slip['upah_lembur'] = $saved->upah_lembur ?? $slip['upah_lembur'];
                $slip['jml_jam_lembur']     = $saved->jml_jam_lembur ?? $slip['jml_jam_lembur'];
                $slip['kekurangan_bulan_lalu'] = $saved->kekurangan_bulan_lalu;
                $slip['tunj_kehadiran']     = $saved->tunj_kehadiran ?? 0;
                $slip['tunj_pulsa']         = $saved->tunj_pulsa    ?? 0;
                $slip['kompensasi_pwt']     = $saved->kompensasi_pwt ?: $slip['kompensasi_pwt'];
                $slip['tunj_jabatan']       = $saved->tunj_jabatan ?? 0;
                $slip['id']                 = $saved->id;
                $slip['pot_tabung_oksigen'] = $saved->pot_tabung_oksigen ?? 0;
                $slip['insentif'] = $saved->insentif ?? $slip['insentif'];
                $slip['stb']                   = $saved->stb ?? $slip['stb'] ?? 0;


                $projectKode = strtolower($emp->project?->kode ?? '');
                if ($projectKode === 'nk') {
                    $slip['potongan_insentif'] = round(
                        $slip['insentif'] / 25 * ($slip['izin'] + $slip['sakit'] + $slip['cuti'] + $slip['stb']),
                        2
                    );
                    $slip['gaji_bersih'] = round(
                        $slip['gaji_kotor']
                        - $slip['potongan_jht']
                        - $slip['potongan_pensiun']
                        - $slip['potongan_kes']
                        - $slip['potongan_alpa']
                        - $slip['potongan_insentif']
                        + ($slip['kekurangan_bulan_lalu'] ?? 0),
                        2
                    );
                }

                if ($isMd) {
                    $slip['ttt_perhari'] = $saved->ttt_perhari ?: $slip['ttt_perhari'];
                    $slip['com_day']     = $saved->com_day     ?: $slip['com_day'];
                }
            }

            if ($isMd) {
                $hKerja      = $slip['h_basic'] ?? 0;
                $hSabtu      = $slip['h_sabtu'] ?? 0;
                $uBasic      = round(($slip['gaji_pokok'] + $slip['tunj_tetap']) / 17 * min($hKerja, 17), 2);
                $uKerja      = round((($slip['tunj_makan'] ?? 0) + ($slip['tunj_kehadiran'] ?? 0)) * ($slip['h_kerja'] ?? 0), 2);
                $comDayTotal = round(($slip['com_day'] ?? 0) * $hSabtu, 2);
                $upahPenuh   = $slip['gaji_pokok'] + $slip['tunj_tetap'];
                $slip['gaji_kotor'] = round(
                    $uBasic
                    + ($slip['kompensasi_pwt'] ?? 0)
                    + $uKerja
                    + $comDayTotal
                    + ($slip['upah_lembur'] ?? 0)
                    + ($slip['tunj_pulsa'] ?? 0)
                    + ($slip['kekurangan_bulan_lalu'] ?? 0),
                    2
                );
                $slip['gaji_bersih'] = round(
                    $slip['gaji_kotor']
                    - round($upahPenuh * 0.02)
                    - round($upahPenuh * 0.01)
                    - round($upahPenuh * 0.01)
                    - ($slip['potongan_alpa'] ?? 0),
                    2
                );
                $slip['u_basic'] = $uBasic;
                $slip['u_kerja'] = $uKerja;
            }

            $slip['sub_group']    = $m->sub_group;
            $slip['urutan']       = $m->urutan ?? 999;
            $slip['project_kode'] = strtolower($emp->project?->kode ?? '');
            $slip['nama_bank']    = $saved->nama_bank    ?? $emp->nama_bank    ?? null;
            $slip['no_rekening']  = $saved->no_rekening  ?? $emp->no_rekening  ?? null;
            $slip['no_bpjs_tk']   = $saved->no_bpjs_tk   ?? $emp->no_bpjs_tk   ?? null;
            $slip['no_bpjs_kes']  = $saved->no_bpjs_kes  ?? $emp->no_bpjs_kes  ?? null;
            $slip['ptkp']         = $saved->ptkp         ?? $emp->ptkp ?? '—';
            $rows[]             = $slip;
            $totalGajiKotor    += $slip['gaji_kotor']  ?? 0;
            $totalGajiBersih   += $slip['gaji_bersih'] ?? 0;
        }

        $tttItems   = $projectId ? $this->getTttItems($projectId) : collect();
        $customKeys = $tttItems->where('is_default', false)->pluck('key')->toArray();

        foreach ($rows as &$row) {
            $saved = $savedPayrolls->get($row['employee_id']);

            foreach ($customKeys as $key) {
                $row[$key] = $saved?->ttt_custom[$key] ?? 0;
            }
        }
        unset($row);

        return [
            'rows'              => $rows,
            'total_gaji_kotor'  => $totalGajiKotor,
            'total_gaji_bersih' => $totalGajiBersih,
            'ttt_items'         => $tttItems,
        ];
    }

    // ════════════════════════════════════════════════════════════
    // SIMPAN DATA GAJI (batch semua member)
    // ════════════════════════════════════════════════════════════
    public function simpanDataGaji(Request $request)
    {
        if ($this->isViewer()) {
            return back()->with('error', 'Viewer tidak memiliki akses.');
        }

        $tahun     = (int) $request->get('tahun', now()->year);
        $bulan     = (int) $request->get('bulan', now()->month);
        $projectId = $this->activeProjectId();

        $members = TimesheetMember::where('aktif', true)
            ->when($projectId, fn($q, $pid) => $q->where('project_id', $pid))
            ->with('employee.position', 'employee.project')
            ->orderBy('urutan')->orderBy('id_badge')
            ->get();

        foreach ($members as $m) {
            $emp = $m->employee;
            if (!$emp) continue;

            $isMd   = $emp->project?->tipe_gaji === 'md';
            $isFlat = $m->kelompok === 'flat';

            // Ambil data yang sudah ada (manual) — supaya field manual tidak ditimpa
            $existing = EmployeePayroll::where([
                'employee_id' => $emp->id,
                'tahun'       => $tahun,
                'bulan'       => $bulan,
            ])->first();

            if ($isMd) {
                $tsData = $this->getTimesheetData($emp->id, $tahun, $bulan);

                // Pakai nilai manual yang sudah ada sebagai override, supaya
                // hitung ulang lembur tetap pakai gaji pokok/tunjangan/TTT yg sudah diedit HR
                $overrideInput = $existing ? [
                    'gaji_pokok'            => $existing->gaji_pokok      ?? $emp->gaji_pokok,
                    'tunj_tetap'            => $existing->tunj_tetap      ?? $emp->tunj_jabatan,
                    'ttt_perhari'           => $existing->ttt_perhari     ?? 15000,
                    'insentif'              => $existing->insentif        ?? 0,
                    'tunj_makan'            => $existing->tunj_makan      ?? 15000,
                    'com_day'               => $existing->com_day         ?? 10000,
                    'kompensasi_pwt'        => $existing->kompensasi_pwt  ?? null,
                    'kekurangan_bulan_lalu' => $existing->kekurangan_bulan_lalu ?? 0,
                    'tunj_kehadiran'        => $existing->tunj_kehadiran  ?? 0,
                    'tunj_pulsa'            => $existing->tunj_pulsa      ?? 0,
                ] : [];

                $slip = $this->hitungSlipGajiMd($emp, $tahun, $bulan, $tsData, $overrideInput);
            } else {
                $slip = $this->hitungSlipGaji($emp, $tahun, $bulan, $isFlat);

                // Override dengan nilai manual yang sudah ada (Non-MD)
                if ($existing) {
                    $gajiPokok    = $existing->gaji_pokok      ?? $slip['gaji_pokok'];
                    $tunjTetap    = $existing->tunj_tetap      ?? $slip['tunj_tetap'];
                    $tunjMakan    = $existing->tunj_makan      ?? $slip['tunj_makan'];
                    $tunjProduksi = $existing->tunj_produksi   ?? $slip['tunj_produksi'];
                    $tunjLapangan = $existing->tunj_lapangan   ?? $slip['tunj_lapangan'];
                    $insentif     = $existing->insentif        ?? $slip['insentif'];
                    $comDay       = $existing->com_day         ?? $slip['com_day'];
                    $uangHadir    = $existing->uang_hadir      ?? $slip['uang_hadir'];
                    $lemburBiasa  = $existing->lembur_biasa    ?? $slip['lembur_biasa'];
                    $lSabtuManual = $existing->l_sabtu         ?? $slip['l_sabtu'];
                    $lLiburManual = $existing->l_libur         ?? $slip['l_libur'];
                    $kekurangan   = $existing->kekurangan_bulan_lalu ?? 0;

                    $kompensasiPwt = ($gajiPokok + $tunjTetap) / 12;
                    $upahPenuh     = $gajiPokok + $tunjTetap;
                    $nilaiPerJam   = $upahPenuh / 173;

                    $projectKode = strtolower($emp->project?->kode ?? '');
                    $isKhawistaPilingFlat = $isFlat
                        && in_array($projectKode, ['khawista', 'nk'])
                        && strtolower($m->sub_group ?? '') === 'piling';

                    if ($isKhawistaPilingFlat) {
                        // Piling pakai lump_sum dari overtime_custom — jangan recalc dari tarif standar
                        $totalLemburFlat = $existing->total_lembur_flat ?? 0;
                        $upahLembur       = $existing->upah_lembur       ?? 0;
                    } elseif ($isFlat) {
                        $totalLemburFlat = ($lSabtuManual * self::TARIF_SABTU)
                            + ($lLiburManual * self::TARIF_LIBUR)
                            + ($lemburBiasa * self::TARIF_BIASA);
                        $upahLembur = $totalLemburFlat;
                    } else {
                        // Per jam: tetap pakai jam lembur HASIL HITUNG ULANG dari timesheet terbaru
                        $upahLembur      = round($nilaiPerJam * $slip['jml_jam_lembur'], 2);
                        $totalLemburFlat = $slip['total_lembur_flat'];
                    }

                    $baseKomponen = $gajiPokok + $tunjTetap + $kompensasiPwt
                        + $comDay + $insentif + $tunjMakan + $tunjProduksi + $tunjLapangan;

                    $gajiKotor = $isFlat
                        ? $baseKomponen + $totalLemburFlat + $uangHadir
                        : $baseKomponen + $upahLembur;

                    $potonganJht     = round($upahPenuh * 0.02);
                    $potonganPensiun = round($upahPenuh * 0.01);
                    $potonganKes     = round($upahPenuh * 0.01);

                    $izinDipotong = !in_array($projectKode, ['khawista', 'purnama']);
                    $potonganAlpa = round($upahPenuh / 25 * ($slip['alpa'] + ($izinDipotong ? $slip['izin'] : 0)), 2);

                    $potonganInsentif = 0;
                    if ($projectKode === 'khawista') {
                        $subGroup = strtolower($m->sub_group ?? '');
                        if ($subGroup === 'construction') {
                            $potonganInsentif = round($insentif / 25 * ($slip['izin'] + $slip['sakit'] + $slip['cuti']), 2);
                        } elseif ($subGroup === 'piling') {
                            $potonganInsentif = round($tunjLapangan / 25 * $slip['izin'], 2);
                        }
                    } elseif ($projectKode === 'nk') {
                        $stbVal = $existing->stb ?? $slip['stb'] ?? 0;
                        $potonganInsentif = round($insentif / 25 * ($slip['izin'] + $slip['sakit'] + $slip['cuti'] + $stbVal), 2);
                    }

                    $potTabungOksigen = $existing->pot_tabung_oksigen ?? 0;
                    $gajiBersih = $gajiKotor - $potonganJht - $potonganPensiun - $potonganKes
                        - $potonganAlpa - $potonganInsentif - $potTabungOksigen + $kekurangan;


                    $slip = array_merge($slip, [
                        'gaji_pokok'            => $gajiPokok,
                        'tunj_tetap'            => $tunjTetap,
                        'kompensasi_pwt'        => round($kompensasiPwt, 2),
                        'tunj_makan'            => $tunjMakan,
                        'tunj_produksi'         => $tunjProduksi,
                        'tunj_lapangan'         => $tunjLapangan,
                        'insentif'              => $insentif,
                        'com_day'               => $comDay,
                        'uang_hadir'            => $uangHadir,
                        'lembur_biasa'          => $lemburBiasa,
                        'l_sabtu'               => $lSabtuManual,
                        'l_libur'               => $lLiburManual,
                        'total_lembur_flat'     => $totalLemburFlat,
                        'upah_lembur'           => $upahLembur,
                        'gaji_kotor'            => round($gajiKotor, 2),
                        'potongan_jht'          => $potonganJht,
                        'potongan_pensiun'      => $potonganPensiun,
                        'potongan_kes'          => $potonganKes,
                        'potongan_alpa'         => $potonganAlpa,
                        'potongan_insentif'     => $potonganInsentif,
                        'pot_tabung_oksigen'    => $potTabungOksigen,
                        'kekurangan_bulan_lalu' => $kekurangan,
                        'gaji_bersih'           => round($gajiBersih, 2),
                    ]);
                }
            }

            EmployeePayroll::updateOrCreate(
                ['employee_id' => $emp->id, 'tahun' => $tahun, 'bulan' => $bulan],
                [
                    'gaji_pokok'            => $slip['gaji_pokok'],
                    'tunj_tetap'            => $slip['tunj_tetap'],
                    'tunj_jabatan'          => $existing->tunj_jabatan ?? $slip['tunj_jabatan'] ?? 0,
                    'kompensasi_pwt'        => $slip['kompensasi_pwt'],
                    'ttt_perhari'           => $slip['ttt_perhari']       ?? 0,
                    'com_day'               => $slip['com_day']           ?? 0,
                    'insentif'              => $slip['insentif']          ?? 0,
                    'tunj_makan'            => $slip['tunj_makan']        ?? 0,
                    'tunj_produksi'         => $slip['tunj_produksi']     ?? 0,
                    'tunj_lapangan'         => $slip['tunj_lapangan']     ?? 0,
                    'jml_jam_lembur'        => $slip['jml_jam_lembur'],
                    'upah_lembur'           => $slip['upah_lembur'],
                    'l_sabtu'               => $slip['l_sabtu'],
                    'l_libur'               => $slip['l_libur'],
                    'lembur_biasa'          => $slip['lembur_biasa'],
                    'total_lembur_flat'     => $slip['total_lembur_flat'],
                    'uang_hadir'            => $slip['uang_hadir'],
                    'h_kerja'               => $slip['h_kerja'],
                    'h_basic'               => $slip['h_basic'] ?? 0,
                    'u_basic'               => $slip['u_basic'] ?? 0,
                    'u_kerja'               => $slip['u_kerja'] ?? 0,
                    'gaji_kotor'            => $slip['gaji_kotor'],
                    'potongan_jht'          => $slip['potongan_jht'],
                    'potongan_pensiun'      => $slip['potongan_pensiun'],
                    'potongan_kes'          => $slip['potongan_kes'],
                    'potongan_alpa'         => $slip['potongan_alpa'],
                    'potongan_insentif'     => $slip['potongan_insentif'] ?? 0,
                    'pot_tabung_oksigen'    => $slip['pot_tabung_oksigen'] ?? 0,
                    'kekurangan_bulan_lalu' => $slip['kekurangan_bulan_lalu'],
                    'gaji_bersih'           => $slip['gaji_bersih'],
                    'izin'                  => $slip['izin'],
                    'sakit'                 => $slip['sakit'],
                    'alpa'                  => $slip['alpa'],
                    'cuti'                  => $slip['cuti'],
                    'stb'                   => $existing->stb ?? $slip['stb'] ?? 0,
                    'ptkp'                  => $emp->ptkp,
                    'tunj_kehadiran'        => $existing->tunj_kehadiran  ?? 0,
                    'tunj_pulsa'            => $existing->tunj_pulsa      ?? 0,
                    'ttt_custom'            => $existing->ttt_custom      ?? null,
                    'dibuat_oleh'           => auth()->user()?->name ?? 'System',
                ]
            );
        }

        ActivityLog::record(
            'update',
            'Data Gaji',
            null,
            "Hitung ulang lembur & potongan {$tahun}/{$bulan} — {$members->count()} karyawan (nilai manual dipertahankan)"
        );

        return back()->with('success', "Lembur & potongan berhasil dihitung ulang untuk {$tahun}/{$bulan}. Nilai manual yang sudah diisi tetap dipertahankan.");
    }

    // ════════════════════════════════════════════════════════════
    // UPDATE MANUAL — field tertentu per karyawan
    // ════════════════════════════════════════════════════════════
    public function updateManual(Request $request, int $employeeId)
    {
        if ($this->isViewer()) {
            return response()->json(['ok' => false, 'message' => 'Viewer tidak memiliki akses.'], 403);
        }


        $data = $request->validate([
            'tahun'                  => 'required|integer',
            'bulan'                  => 'required|integer',
            'gaji_pokok'             => 'nullable|numeric|min:0',
            'tunj_tetap'             => 'nullable|numeric|min:0',
            'ttt_perhari'            => 'nullable|numeric|min:0',
            'lembur_biasa'           => 'nullable|integer|min:0',
            'l_sabtu'                => 'nullable|integer|min:0',
            'l_libur'                => 'nullable|integer|min:0',
            'potongan_alpa'          => 'nullable|numeric|min:0',
            'kekurangan_bulan_lalu'  => 'nullable|numeric',
            'tunj_makan'             => 'nullable|numeric|min:0',
            'tunj_produksi'          => 'nullable|numeric|min:0',
            'tunj_lapangan'          => 'nullable|numeric|min:0',
            'insentif'               => 'nullable|numeric|min:0',
            'com_day'                => 'nullable|numeric|min:0',
            'uang_hadir'             => 'nullable|numeric|min:0',
            'nama_bank'              => 'nullable|string|max:50',
            'no_rekening'            => 'nullable|string|max:50',
            'no_bpjs_tk'             => 'nullable|string|max:50',
            'no_bpjs_kes'            => 'nullable|string|max:50',
            'catatan'                => 'nullable|string|max:500',
            'tunj_kehadiran'         => 'nullable|numeric|min:0',
            'tunj_pulsa'             => 'nullable|numeric|min:0',
            'kompensasi_kontrak'     => 'nullable|numeric|min:0',
            'kompensasi_pwt'         => 'nullable|numeric|min:0',
            'ttt_custom'             => 'nullable|array',
            'ttt_custom.*'           => 'nullable|numeric|min:0',
        ]);

        $tahun = $data['tahun'];
        $bulan = $data['bulan'];
        $emp   = Employee::with(['position', 'project'])->findOrFail($employeeId);
        $isMd  = $emp->project?->tipe_gaji === 'md';

        $payroll = EmployeePayroll::firstOrNew([
            'employee_id' => $employeeId,
            'tahun'       => $tahun,
            'bulan'       => $bulan,
        ]);

        if ($isMd) {
            $tsData = $this->getTimesheetData($emp->id, $tahun, $bulan);
            $slip   = $this->hitungSlipGajiMd($emp, $tahun, $bulan, $tsData);

            $overrideInput = array_merge($slip, [
                'gaji_pokok'            => isset($data['gaji_pokok'])    ? (float) $data['gaji_pokok']    : $slip['gaji_pokok'],
                'tunj_tetap'            => isset($data['tunj_tetap'])    ? (float) $data['tunj_tetap']    : $slip['tunj_tetap'],
                'ttt_perhari'           => isset($data['ttt_perhari'])   ? (float) $data['ttt_perhari']   : $slip['ttt_perhari'],
                'insentif'              => isset($data['insentif'])      ? (float) $data['insentif']      : $slip['insentif'],
                'tunj_makan'            => isset($data['tunj_makan'])    ? (float) $data['tunj_makan']    : $slip['tunj_makan'],
                'com_day'               => isset($data['com_day'])       ? (float) $data['com_day']       : $slip['com_day'],
                'kompensasi_pwt'        => isset($data['kompensasi_pwt'])? (float) $data['kompensasi_pwt']: $slip['kompensasi_pwt'],
                'kekurangan_bulan_lalu' => $data['kekurangan_bulan_lalu'] ?? 0,
                'tahun'                 => $tahun,
                'bulan'                 => $bulan,
                'tunj_kehadiran'        => $data['tunj_kehadiran'] ?? 0,
                'tunj_pulsa'            => $data['tunj_pulsa']     ?? 0,
            ]);

            $slip = $this->hitungSlipGajiMd($emp, $tahun, $bulan, $tsData, $overrideInput);

        } else {
            // Baca kelompok dari TimesheetMember
            $member = TimesheetMember::where('id_badge', $emp->id_badge)
                ->where('project_id', $this->activeProjectId())
                ->first();
            $isFlat = $member?->kelompok === 'flat';

            $slip      = $this->hitungSlipGaji($emp, $tahun, $bulan, $isFlat);
            $gajiPokok = isset($data['gaji_pokok']) ? (float) $data['gaji_pokok'] : $slip['gaji_pokok'];
            $tunjTetap = isset($data['tunj_tetap']) ? (float) $data['tunj_tetap'] : $slip['tunj_tetap'];

            $kompensasiPwt   = ($gajiPokok + $tunjTetap) / 12;
            $dul             = $gajiPokok + $tunjTetap;
            $upahPenuh       = $dul;
            $lemburBiasa     = $data['lembur_biasa'] ?? $slip['lembur_biasa'];
            $lSabtu          = isset($data['l_sabtu']) ? (int) $data['l_sabtu'] : $slip['l_sabtu'];
            $lLibur          = isset($data['l_libur']) ? (int) $data['l_libur'] : $slip['l_libur'];
            $totalLemburFlat = ($lSabtu * self::TARIF_SABTU) + ($lLibur * self::TARIF_LIBUR) + ($lemburBiasa * self::TARIF_BIASA);
            $tunjMakan       = $data['tunj_makan']       ?? $slip['tunj_makan'];
            $tunjProduksi    = $data['tunj_produksi']    ?? $slip['tunj_produksi'];
            $tunjLapangan    = $data['tunj_lapangan']    ?? $slip['tunj_lapangan'];
            $insentif        = $data['insentif']         ?? $slip['insentif'];
            $comDay          = $data['com_day']          ?? $slip['com_day'];
            $uangHadir       = $data['uang_hadir']       ?? $slip['uang_hadir'];
            $kekurangan      = $data['kekurangan_bulan_lalu'] ?? 0;
            $nilaiPerJam     = $dul / 173;
            $upahLembur      = $isFlat
                ? $totalLemburFlat
                : round($slip['jml_jam_lembur'] * $nilaiPerJam, 2);


            $baseKomponen = $gajiPokok + $tunjTetap + $kompensasiPwt
                + $comDay + $insentif + $tunjMakan + $tunjProduksi + $tunjLapangan;

            $gajiKotor = $isFlat
                ? $baseKomponen + $totalLemburFlat + $uangHadir
                : $baseKomponen + $upahLembur;

            $potonganJht     = round($upahPenuh * 0.02);
            $potonganPensiun = round($upahPenuh * 0.01);
            $potonganKes     = round($upahPenuh * 0.01);

            $projectKode  = strtolower($emp->project?->kode ?? '');
            $izinDipotong = !in_array($projectKode, ['khawista', 'purnama']);
            $potonganAlpa = round($upahPenuh / 25 * ($slip['alpa'] + ($izinDipotong ? $slip['izin'] : 0)), 2);

            // Potongan khusus Khawista & NK
            $potonganInsentif = 0;
            if ($projectKode === 'khawista') {
                $subGroup = strtolower($member?->sub_group ?? '');
                if ($subGroup === 'construction') {
                    $potonganInsentif = round($insentif / 25 * ($slip['izin'] + $slip['sakit'] + $slip['cuti']), 2);
                } elseif ($subGroup === 'piling') {
                    $potonganInsentif = round($tunjLapangan / 25 * $slip['izin'], 2);
                }
            } elseif ($projectKode === 'nk') {
                $stbVal = $slip['stb'] ?? 0;
                $potonganInsentif = round($insentif / 25 * ($slip['izin'] + $slip['sakit'] + $slip['cuti'] + $stbVal), 2);
            }

            $potTabungOksigen = $data['pot_tabung_oksigen'] ?? ($payroll->pot_tabung_oksigen ?? 0);
            $gajiBersih = $gajiKotor - $potonganJht - $potonganPensiun - $potonganKes - $potonganAlpa - $potonganInsentif - $potTabungOksigen + $kekurangan;


            $slip = array_merge($slip, [
                'gaji_pokok'            => $gajiPokok,
                'tunj_tetap'            => $tunjTetap,
                'kompensasi_pwt'        => round($kompensasiPwt, 2),
                'lembur_biasa'          => $lemburBiasa,
                'l_sabtu'               => $lSabtu,
                'l_libur'               => $lLibur,
                'total_lembur_flat'     => $totalLemburFlat,
                'upah_lembur'           => $upahLembur,
                'tunj_makan'            => $tunjMakan,
                'tunj_produksi'         => $tunjProduksi,
                'tunj_lapangan'         => $tunjLapangan,
                'insentif'              => $insentif,
                'com_day'               => $comDay,
                'uang_hadir'            => $uangHadir,
                'gaji_kotor'            => round($gajiKotor, 2),
                'potongan_jht'          => $potonganJht,
                'potongan_pensiun'      => $potonganPensiun,
                'potongan_kes'          => $potonganKes,
                'potongan_alpa'         => $potonganAlpa,
                'potongan_insentif'     => $potonganInsentif,
                'pot_tabung_oksigen'    => $potTabungOksigen,
                'kekurangan_bulan_lalu' => $kekurangan,
                'gaji_bersih'           => round($gajiBersih, 2),
                'tunj_kehadiran'        => $data['tunj_kehadiran'] ?? 0,
                'tunj_pulsa'            => $data['tunj_pulsa']     ?? 0,
            ]);
        }

        $updateData = array_merge($slip, [
            'tunj_kehadiran' => $data['tunj_kehadiran'] ?? 0,
            'tunj_pulsa'     => $data['tunj_pulsa']     ?? 0,
            'nama_bank'      => $data['nama_bank']      ?? null,
            'no_rekening'    => $data['no_rekening']    ?? null,
            'no_bpjs_tk'     => $data['no_bpjs_tk']     ?? null,
            'no_bpjs_kes'    => $data['no_bpjs_kes']    ?? null,
            'catatan'        => $data['catatan']         ?? null,
            'dibuat_oleh'    => auth()->user()?->name   ?? 'System',
        ]);

        // Merge ttt_custom dengan yang sudah ada, tidak overwrite semua
        if (!empty($data['ttt_custom'])) {
            $existing = $payroll->ttt_custom ?? [];
            $updateData['ttt_custom'] = array_merge($existing, $data['ttt_custom']);
        }

        $allowedColumns = [
            'gaji_pokok','tunj_tetap','tunj_jabatan','kompensasi_pwt','upah_penuh',
            'ttt_perhari','dul','com_day','insentif','tunj_makan','tunj_produksi',
            'tunj_lapangan','tunj_kehadiran','tunj_pulsa','kompensasi_kontrak',
            'ttt_custom','jml_jam_lembur','total_jam_ot_15x','total_jam_ot_2x',
            'upah_lembur','l_sabtu','l_libur','lembur_biasa','total_lembur_flat',
            'uang_hadir','h_kerja','h_sabtu','h_minggu_libur',
            'gaji_kotor','potongan_jht','potongan_pensiun','potongan_kes',
            'potongan_alpa','potongan_insentif','pot_tabung_oksigen','kekurangan_bulan_lalu','gaji_bersih',
            'izin','sakit','alpa','cuti','stb',
            'nama_bank','no_rekening','ptkp','no_bpjs_tk','no_bpjs_kes',
            'dibuat_oleh','catatan',
        ];

        foreach ($updateData as $k => $v) {
            if (in_array($k, $allowedColumns)) {
                $payroll->$k = $v;
            }
        }
        $payroll->save();

        $empLog = Employee::find($employeeId);
        ActivityLog::record(
            'update',
            'Data Gaji',
            $empLog?->nama_lengkap ?? "ID:{$employeeId}",
            "Update manual data gaji {$tahun}/{$bulan}: {$empLog?->nama_lengkap} ({$empLog?->id_badge})"
        );

        return response()->json(['ok' => true, 'message' => 'Berhasil disimpan.']);
    }


    // ════════════════════════════════════════════════════════════
    // EXPORT SLIP EXCEL
    // ════════════════════════════════════════════════════════════
    public function exportSlipExcel(Request $request)
    {
        $tahun      = (int) $request->get('tahun', now()->year);
        $bulan      = (int) $request->get('bulan', now()->month);
        $employeeId = $request->get('employee_id');
        $pctJht     = (float) $request->get('pct_jht', 2);
        $pctPensiun = (float) $request->get('pct_pensiun', 1);
        $pctKes     = (float) $request->get('pct_kes', 1);
        $ttdRaw     = $request->get('ttd', '[]');
        $ttdList    = json_decode($ttdRaw, true) ?: [];

        $employee = Employee::with(['position', 'project'])->findOrFail($employeeId);
        $isMd     = $employee->project?->tipe_gaji === 'md';

        // Ambil kelompok dari TimesheetMember
        $projectId = $this->activeProjectId();
        $member    = TimesheetMember::where('id_badge', $employee->id_badge)
            ->when($projectId, fn($q) => $q->where('project_id', $projectId))
            ->first();
        $isFlat    = $member?->kelompok === 'flat';

        if ($isMd) {
            $tsData = $this->getTimesheetData($employee->id, $tahun, $bulan);
            $slip   = $this->hitungSlipGajiMd($employee, $tahun, $bulan, $tsData);
        } else {
            $slip = $this->hitungSlipGaji($employee, $tahun, $bulan, $isFlat);
        }

        // Override dengan data yang sudah disimpan di DB (sama seperti slipGaji())
        $savedPayroll = EmployeePayroll::where([
            'employee_id' => $employee->id,
            'tahun'       => $tahun,
            'bulan'       => $bulan,
        ])->first();

        if ($savedPayroll) {
            $slip['tunj_makan']         = $savedPayroll->tunj_makan         ?? $slip['tunj_makan'];
            $slip['tunj_produksi']      = $savedPayroll->tunj_produksi      ?? $slip['tunj_produksi'];
            $slip['tunj_lapangan']      = $savedPayroll->tunj_lapangan      ?? $slip['tunj_lapangan'];
            $slip['tunj_kehadiran']     = $savedPayroll->tunj_kehadiran     ?? 0;
            $slip['tunj_pulsa']         = $savedPayroll->tunj_pulsa         ?? 0;
            $slip['kompensasi_kontrak'] = $savedPayroll->kompensasi_kontrak ?? 0;
            $slip['insentif']           = $savedPayroll->insentif           ?? $slip['insentif'];
            $slip['com_day']            = $savedPayroll->com_day            ?? $slip['com_day'];
            $slip['gaji_pokok']         = $savedPayroll->gaji_pokok !== null ? $savedPayroll->gaji_pokok : $slip['gaji_pokok'];
            $slip['tunj_tetap']         = $savedPayroll->tunj_tetap !== null ? $savedPayroll->tunj_tetap : $slip['tunj_tetap'];
            $slip['kekurangan_bulan_lalu'] = $savedPayroll->kekurangan_bulan_lalu ?? 0;
            $slip['upah_lembur']        = $savedPayroll->upah_lembur        ?? $slip['upah_lembur'];
            $slip['potongan_alpa']      = $savedPayroll->potongan_alpa      ?? $slip['potongan_alpa'];
            $slip['potongan_insentif']  = $savedPayroll->potongan_insentif  ?? 0;

            $upahPenuh = $slip['gaji_pokok'] + $slip['tunj_tetap'];


            if (!$isMd) {
                $kompPwt    = $upahPenuh / 12;
                $tttSum     = ($slip['tunj_makan'] ?? 0) + ($slip['tunj_produksi'] ?? 0)
                            + ($slip['tunj_lapangan'] ?? 0) + ($slip['tunj_kehadiran'] ?? 0)
                            + ($slip['tunj_pulsa'] ?? 0) + ($slip['kompensasi_kontrak'] ?? 0)
                            + ($slip['insentif'] ?? 0) + ($slip['com_day'] ?? 0);
                $slip['kompensasi_pwt'] = round($kompPwt, 2);
                $slip['gaji_kotor']     = round(
                    $slip['gaji_pokok'] + $slip['tunj_tetap'] + $kompPwt + $tttSum + $slip['upah_lembur']
                );
                $slip['gaji_bersih']    = round(
                    $slip['gaji_kotor']
                    - round($upahPenuh * 0.02)
                    - round($upahPenuh * 0.01)
                    - round($upahPenuh * 0.01)
                    - ($slip['potongan_alpa'] ?? 0)
                    - ($slip['potongan_insentif'] ?? 0)
                );
            } else {
                $hKerja      = $slip['h_basic'] ?? 0;
                $hSabtu      = $slip['h_sabtu'] ?? 0;
                $uBasic      = round($upahPenuh / 17 * min($hKerja, 17), 2);
                $uKerja      = round((($slip['tunj_makan'] ?? 0) + ($slip['tunj_kehadiran'] ?? 0)) * ($slip['h_kerja'] ?? 0), 2);
                $comDayTotal = round(($slip['com_day'] ?? 0) * $hSabtu, 2);
                $slip['u_basic']        = $uBasic;
                $slip['u_kerja']        = $uKerja;
                $slip['kompensasi_pwt'] = $savedPayroll->kompensasi_pwt ?? round($upahPenuh / 12, 2);
                $slip['gaji_kotor']     = round(
                    $uBasic
                    + $slip['kompensasi_pwt']
                    + $uKerja
                    + $comDayTotal
                    + ($slip['upah_lembur'] ?? 0)
                    + ($slip['tunj_pulsa'] ?? 0)
                    + ($slip['kekurangan_bulan_lalu'] ?? 0),
                    2
                );
                $slip['gaji_bersih']    = round(
                    $slip['gaji_kotor']
                    - round($upahPenuh * 0.02)
                    - round($upahPenuh * 0.01)
                    - round($upahPenuh * 0.01)
                    - ($slip['potongan_alpa'] ?? 0),
                    2
                );
            }
        }


        $bulanNama  = $this->bulanNama();
        $periodeStr = $bulanNama[$bulan] . ' ' . $tahun;
        $upahPenuh  = $slip['gaji_pokok'] + $slip['tunj_tetap'];
        $potJht     = round($upahPenuh * $pctJht / 100);
        $potPensiun = round($upahPenuh * $pctPensiun / 100);
        $potKes     = round($upahPenuh * $pctKes / 100);
        $potAlpa    = $slip['potongan_alpa'];
        $totalPot   = $potJht + $potPensiun + $potKes + $potAlpa;
        $gajiPokok  = $slip['gaji_pokok'];
        $tunjTetap  = $slip['tunj_tetap'];
        $kompPwt    = $slip['kompensasi_pwt'];
        $upahLembur = $slip['upah_lembur'];
        $gajiKotor  = $slip['gaji_kotor'];
        $gajiBersih = $gajiKotor - $totalPot + ($slip['kekurangan_bulan_lalu'] ?? 0);

        $ss = new Spreadsheet();
        $ws = $ss->getActiveSheet();
        $ws->setTitle('Slip Gaji');

        $ws->getPageSetup()
            ->setPaperSize(PageSetup::PAPERSIZE_A5)
            ->setOrientation(PageSetup::ORIENTATION_PORTRAIT)
            ->setFitToPage(true)->setFitToWidth(1)->setFitToHeight(0);
        $ws->getPageMargins()->setTop(0.5)->setRight(0.4)->setBottom(0.5)->setLeft(0.4);
        $ws->getColumnDimension('A')->setWidth(14.4);
        $ws->getColumnDimension('B')->setWidth(1.7);
        $ws->getColumnDimension('C')->setWidth(30);
        $ws->getColumnDimension('D')->setWidth(3);
        $ws->getColumnDimension('E')->setWidth(14);
        $ws->getColumnDimension('F')->setWidth(3);
        $ws->getColumnDimension('G')->setWidth(14);

        $fmtRp              = fn($n) => $n !== null ? number_format((float) $n, 0, ',', '.') : '-';
        $borderThin         = ['borders' => ['outline' => ['borderStyle' => Border::BORDER_THIN]]];
        $borderBottom       = ['borders' => ['bottom'  => ['borderStyle' => Border::BORDER_THIN]]];
        $borderTopMed       = ['borders' => ['top'     => ['borderStyle' => Border::BORDER_MEDIUM]]];
        $borderTopBottomMed = ['borders' => [
            'top'    => ['borderStyle' => Border::BORDER_MEDIUM],
            'bottom' => ['borderStyle' => Border::BORDER_MEDIUM],
        ]];

        $r = 1;

        // Header
        $ws->mergeCells("A{$r}:G{$r}");
        $ws->setCellValue("A{$r}", 'PT. ANDALAS KARYA MULIA');
        $ws->getStyle("A{$r}")->applyFromArray(['font' => ['bold' => true, 'size' => 12, 'name' => 'Arial'], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);
        $ws->getRowDimension($r)->setRowHeight(16);
        $r++;

        $ws->mergeCells("A{$r}:G{$r}");
        $ws->setCellValue("A{$r}", 'Jl. Wonosari, Komplek Wonosari Regency Blok B No.1, Tangkerang Selatan, Pekanbaru - Riau');
        $ws->getStyle("A{$r}")->applyFromArray(['font' => ['size' => 8, 'name' => 'Arial'], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);
        $r++;

        $ws->getStyle("A{$r}:G{$r}")->applyFromArray($borderTopMed);
        $ws->mergeCells("A{$r}:D{$r}");
        $ws->setCellValue("A{$r}", 'SLIP GAJI KARYAWAN');
        $ws->getStyle("A{$r}")->applyFromArray(['font' => ['bold' => true, 'size' => 11, 'name' => 'Arial'], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]]);
        $ws->mergeCells("E{$r}:G{$r}");
        $ws->setCellValue("E{$r}", 'Periode : ' . $periodeStr);
        $ws->getStyle("E{$r}")->applyFromArray(['font' => ['size' => 9, 'name' => 'Arial'], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]]);
        $ws->getStyle("A{$r}:G{$r}")->applyFromArray($borderBottom);
        $r++;
        $r++;

        // Identitas
        $rowNoReg   = $r; 
        $rowNama    = $r+1;
        $rowJabatan = $r+2;
        $rowNoRek   = $r+3;
        $rowPtkp    = $r+4;
        $r += 5;

        foreach ([
            [$rowNoReg,   'No. Register',  $employee->id_badge],
            [$rowNama,    'Nama Karyawan', $employee->nama_lengkap],
            [$rowJabatan, 'Jabatan',       $employee->position?->nama_jabatan ?? '-'],
            [$rowNoRek,   'No. Rekening',  "\t" . ($employee->no_rekening ?? '-')],
            [$rowPtkp,    'PTKP',          $employee->ptkp ?? '-'],
        ] as [$rowNum, $lbl, $val]) {
            $ws->setCellValue("A{$rowNum}", $lbl);
            $ws->setCellValue("B{$rowNum}", ':');
            $ws->mergeCells("C{$rowNum}:D{$rowNum}");
            $ws->setCellValue("C{$rowNum}", $val);
            $ws->getStyle("A{$rowNum}")->getFont()->setSize(9)->setName('Arial');
            $ws->getStyle("C{$rowNum}")->getFont()->setBold(true)->setSize(9)->setName('Arial');
            $ws->getRowDimension($rowNum)->setRowHeight(14);
        }

        $ws->getRowDimension($rowNoRek)->setRowHeight(20);

        $ws->mergeCells("E{$rowNama}:G{$rowNama}");
        $ws->setCellValue("E{$rowNama}", 'GAJI BERSIH (NETTO)');
        $ws->getStyle("E{$rowNama}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 8, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $ws->mergeCells("E{$rowJabatan}:G{$rowJabatan}");
        $ws->setCellValue("E{$rowJabatan}", 'Rp ' . $fmtRp($gajiBersih));
        $ws->getStyle("E{$rowJabatan}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $ws->getStyle("E{$rowJabatan}:G{$rowJabatan}")->applyFromArray($borderThin);

        $ws->getRowDimension($rowJabatan)->setRowHeight(25);

        $r++;

        // Helper closures
        $addSectionHeader = function (string $letter, string $title) use ($ws, &$r) {
            $ws->mergeCells("A{$r}:G{$r}");
            $ws->setCellValue("A{$r}", ($letter ? $letter . '.   ' : '') . $title);
            $ws->getStyle("A{$r}")->applyFromArray(['font' => ['bold' => true, 'size' => 9, 'name' => 'Arial'], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE8E8E8']]]);
            $ws->getRowDimension($r)->setRowHeight(13);
            $r++;
        };

        $addItem = function (string $no, string $nama, $nilai, string $sub = '') use ($ws, &$r, $fmtRp) {
            $ws->setCellValue("A{$r}", $no);
            $ws->mergeCells("C{$r}:D{$r}");
            $ws->setCellValue("C{$r}", $nama);
            if ($sub) {
                $ws->setCellValue("E{$r}", $sub);
                $ws->getStyle("E{$r}")->getFont()->setSize(7)->setName('Arial')->setColor(new Color('FF888888'));
            }
            $ws->setCellValue("F{$r}", '=');
            $ws->setCellValue("G{$r}", $nilai !== null ? 'Rp ' . $fmtRp($nilai) : '-');
            $ws->getStyle("A{$r}:G{$r}")->getFont()->setSize(9)->setName('Arial');
            $ws->getStyle("G{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $ws->getRowDimension($r)->setRowHeight(13);
            $r++;
        };

        $addTotal = function (string $label, $nilai, bool $bold = false) use ($ws, &$r, $fmtRp, $borderTopBottomMed, $borderTopMed) {
            $ws->mergeCells("A{$r}:F{$r}");
            $ws->setCellValue("A{$r}", $label);
            $ws->setCellValue("G{$r}", 'Rp ' . $fmtRp($nilai));
            $ws->getStyle("A{$r}:G{$r}")->applyFromArray(['font' => ['bold' => $bold, 'size' => 9, 'name' => 'Arial']]);
            $ws->getStyle("G{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $ws->getStyle("A{$r}:G{$r}")->applyFromArray($bold ? $borderTopBottomMed : $borderTopMed);
            $ws->getRowDimension($r)->setRowHeight(14);
            $r++;
        };

        // A. Perolehan
        $addSectionHeader('A', 'PEROLEHAN');
        $addItem('1.', 'Gaji Pokok / Upah', $gajiPokok);
        $addItem('2.', 'Tunjangan Tetap (Tunj. Jabatan)', $tunjTetap);
        $addItem('3.', 'Kompensasi PWT', $kompPwt);

        // B. TTT
        if ($isMd) {
            $addSectionHeader('B', 'TUNJANGAN TIDAK TETAP (MD)');
            $addItem('1.', 'TTT per Hari (× ' . ($slip['h_kerja'] ?? 0) . ' hari)', $slip['ttt_total'] ?? 0, 'Rp ' . number_format($slip['ttt_perhari'] ?? 0, 0, ',', '.') . ' × ' . ($slip['h_kerja'] ?? 0));
            $addItem('2.', 'Uang Makan per Hari (× ' . ($slip['h_kerja'] ?? 0) . ' hari)', $slip['tunj_makan_total'] ?? 0, 'Rp ' . number_format($slip['tunj_makan'] ?? 0, 0, ',', '.') . ' × ' . ($slip['h_kerja'] ?? 0));
            $addItem('3.', 'Com Day (× ' . ($slip['h_kerja'] ?? 0) . ' hari)', $slip['com_day_total'] ?? 0, 'Rp ' . number_format($slip['com_day'] ?? 0, 0, ',', '.') . ' × ' . ($slip['h_kerja'] ?? 0));
            if ($slip['insentif'] ?? 0) $addItem('4.', 'Insentif', $slip['insentif']);
        } else {
            $addSectionHeader('B', 'TUNJANGAN TIDAK TETAP');
            if ($slip['tunj_makan']    ?? 0) $addItem('1.', 'Uang Makan',      $slip['tunj_makan']);
            if ($slip['tunj_produksi'] ?? 0) $addItem('2.', 'Tunj. Produksi',  $slip['tunj_produksi']);
            if ($slip['tunj_lapangan'] ?? 0) $addItem('3.', 'Tunj. Lapangan',  $slip['tunj_lapangan']);
            if ($slip['insentif']      ?? 0) $addItem('4.', 'Insentif',        $slip['insentif']);
        }

        // C. Lembur
        $addSectionHeader('C', 'LEMBUR');
        if ($isMd) {
            $addItem('1.', 'OT 1,5× — ' . ($slip['total_ot_15x'] ?? 0) . ' jam', null, ($slip['total_ot_15x'] ?? 0) . ' × 1,5 × Rp ' . number_format($slip['nilai_lembur_per_jam'] ?? 0, 0, ',', '.') . '/jam');
            $addItem('2.', 'OT 2×   — ' . ($slip['total_ot_2x']  ?? 0) . ' jam', null, ($slip['total_ot_2x']  ?? 0) . ' × 2 × Rp '   . number_format($slip['nilai_lembur_per_jam'] ?? 0, 0, ',', '.') . '/jam');
            $addItem('', 'Total Upah Lembur', $upahLembur);
        } elseif ($isFlat) {
            $addItem('1.', 'Lembur Sabtu × ' . ($slip['l_sabtu'] ?? 0) . ' hari', ($slip['l_sabtu'] ?? 0) * ($slip['tarif_sabtu'] ?? 0), ($slip['l_sabtu'] ?? 0) . ' × Rp ' . number_format($slip['tarif_sabtu'] ?? 0, 0, ',', '.'));
            $addItem('2.', 'Lembur Libur × ' . ($slip['l_libur'] ?? 0) . ' hari', ($slip['l_libur'] ?? 0) * ($slip['tarif_libur'] ?? 0), ($slip['l_libur'] ?? 0) . ' × Rp ' . number_format($slip['tarif_libur'] ?? 0, 0, ',', '.'));
            if ($slip['lembur_biasa'] ?? 0) $addItem('3.', 'Lembur Biasa × ' . ($slip['lembur_biasa'] ?? 0) . ' hari', ($slip['lembur_biasa'] ?? 0) * ($slip['tarif_biasa'] ?? 0), ($slip['lembur_biasa'] ?? 0) . ' × Rp ' . number_format($slip['tarif_biasa'] ?? 0, 0, ',', '.'));
            $addItem('', 'Total Lembur Flat', $slip['total_lembur_flat'] ?? 0);
        } else {
            $addItem('1.', 'Upah Lembur (' . ($slip['jml_jam_lembur'] ?? 0) . ' jam)', $upahLembur);
        }

        $addTotal('GAJI SEBULAN (KOTOR)', $gajiKotor, true);

        // D. Potongan
        $addSectionHeader('D', 'POTONGAN WAJIB');
        $addItem('1.', "BPJS TK – JHT ({$pctJht}%)",        $potJht);
        $addItem('2.', "BPJS TK – Pensiun ({$pctPensiun}%)", $potPensiun);
        $addItem('3.', "BPJS Kesehatan ({$pctKes}%)",        $potKes);
        if ($potAlpa > 0) $addItem('4.', 'Potongan Alpa (' . ($slip['alpa'] ?? 0) . ' hari)', $potAlpa, '(Gapok+Tunj) / 25 × ' . ($slip['alpa'] ?? 0));
        $addTotal('TOTAL POTONGAN', $totalPot);
        $r++;

        // Netto
        $ws->mergeCells("A{$r}:F{$r}");
        $ws->setCellValue("A{$r}", 'PENGHASILAN BERSIH (NETTO)');
        $ws->setCellValue("G{$r}", 'Rp ' . $fmtRp($gajiBersih));
        $ws->getStyle("A{$r}:G{$r}")->applyFromArray(['font' => ['bold' => true, 'size' => 11, 'name' => 'Arial'], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF0F0F0']], 'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM], 'bottom' => ['borderStyle' => Border::BORDER_MEDIUM]]]);
        $ws->getStyle("G{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $ws->getRowDimension($r)->setRowHeight(18);
        $r++;
        $r++;

        // Tanggal
        $ws->mergeCells("A{$r}:G{$r}");
        $ws->setCellValue("A{$r}", 'Pekanbaru, ' . now()->isoFormat('D MMMM Y'));
        $ws->getStyle("A{$r}")->getFont()->setSize(8)->setName('Arial');
        $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $r++;
        $r++;

        // TTD
        $defaultTtd = [
            ['label' => 'Disetujui Oleh,', 'name' => 'H. Syahrul Akmal',    'jabatan' => 'Direktur Utama'],
            ['label' => 'Dibayar Oleh,',   'name' => 'Yulhamdani',          'jabatan' => 'Finance'],
            ['label' => 'Diterima Oleh,',  'name' => $employee->nama_lengkap, 'jabatan' => $employee->position?->nama_jabatan ?? ''],
        ];

        $ttdMerged = [];
        $maxCols   = max(count($ttdList), count($defaultTtd));
        for ($i = 0; $i < $maxCols; $i++) {
            $fe  = $ttdList[$i] ?? [];
            $def = $defaultTtd[$i] ?? ['label' => '', 'name' => '', 'jabatan' => ''];
            $ttdMerged[] = [
                'label'   => $fe['label']   ?? $def['label'],
                'name'    => (isset($fe['name'])    && $fe['name']    !== '') ? $fe['name']    : $def['name'],
                'jabatan' => (isset($fe['jabatan']) && $fe['jabatan'] !== '') ? $fe['jabatan'] : $def['jabatan'],
            ];
        }

        $colMap    = ['A', 'C', 'E', 'G', 'I'];
        $colsToUse = array_slice($colMap, 0, count($ttdMerged));

        foreach ($colsToUse as $i => $col) {
            $ws->setCellValue("{$col}{$r}", $ttdMerged[$i]['label']);
            $ws->getStyle("{$col}{$r}")->applyFromArray(['font' => ['bold' => true, 'size' => 8, 'name' => 'Arial'], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);
        }
        $r += 4;
        foreach ($colsToUse as $i => $col) {
            $ws->setCellValue("{$col}{$r}", $ttdMerged[$i]['name']);
            $ws->getStyle("{$col}{$r}")->applyFromArray(['font' => ['bold' => true, 'size' => 8, 'name' => 'Arial'], 'borders' => ['top' => ['borderStyle' => Border::BORDER_THIN]], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);
        }
        $r++;
        foreach ($colsToUse as $i => $col) {
            $ws->setCellValue("{$col}{$r}", $ttdMerged[$i]['jabatan']);
            $ws->getStyle("{$col}{$r}")->applyFromArray(['font' => ['italic' => true, 'size' => 8, 'name' => 'Arial'], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);
        }

        $namaFile = 'SlipGaji_' . $employee->id_badge . '_' . str_replace(' ', '_', $periodeStr) . '.xlsx';
        $writer   = new Xlsx($ss);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $namaFile . '"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
        exit;
    }

    // ════════════════════════════════════════════════════════════
    // CORE: Hitung slip gaji GIAM / 7 jam
    // ════════════════════════════════════════════════════════════
    private function hitungSlipGaji(Employee $emp, int $tahun, int $bulan, bool $isFlat = false): array
    {
        if (!$emp->relationLoaded('project')) {
            $emp->load('project');
        }


        $gajiPokok      = (float) ($emp->gaji_pokok   ?? 0);
        $tunjTetap      = (float) ($emp->tunj_jabatan ?? 0);
        $tunjJabatan    = 0;
        $kompensasiPwt  = ($gajiPokok + $tunjTetap) / 12;
        $dul            = $gajiPokok + $tunjTetap;
        $nilaiPerJam    = $dul / 173;

        $timesheets = Timesheet::where([
            'employee_id' => $emp->id,
            'tahun'       => $tahun,
            'bulan'       => $bulan,
        ])->get()->keyBy('hari');

        $holidays    = Holiday::inMonth($tahun, $bulan)->get()->keyBy(fn($h) => (int) $h->tanggal->format('j'));
        $daysInMonth = Carbon::create($tahun, $bulan)->daysInMonth;

        $hariDetails    = [];
        $totalJamLembur = 0.0;
        $nominalLembur  = 0.0;
        $hKerja = $izin = $sakit = $alpa = $cuti = 0;
        $lSabtu = $lLibur = $stb = 0;

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date    = Carbon::create($tahun, $bulan, $d);
            $isSun   = $date->isSunday();
            $isSat   = $date->isSaturday();
            $isHol   = isset($holidays[$d]);
            $isLibur = $isSun || $isHol;

            if ($isSun || $isHol) {
                $statusHari = 'L';
            } elseif ($isSat) {
                $statusHari = 'P';
                $isLibur    = false;
            } else {
                $statusHari = 'R';
                $isLibur    = false;
            }

            $ts       = $timesheets->get($d);
            $val      = $ts?->nilai;
            $jamKerja = 0;

            if ($val !== null && $val !== '') {
                $up = strtoupper((string) $val);
                if      ($up === 'I') { $izin++;  }
                elseif  ($up === 'S') { $sakit++; }
                elseif  ($up === 'A') { $alpa++;  }
                elseif  ($up === 'C') { $cuti++;  }
                elseif ($up === 'STB') { $stb++; }
                elseif  (is_numeric($val)) {
                    $jamKerja = (float) $val;
                    $hKerja++;
                    if ($isSat && !$isHol && $jamKerja > 0) $lSabtu++;
                    if ($isLibur && $jamKerja > 0)          $lLibur++;
                }
            }

            $jamReguler = $lembur1_5x = $lembur2x = $lembur3x = $lembur4x = 0;
            $totalJamHari = $nominalHari = 0;

            if (!$isFlat && $jamKerja > 0) {
                if ($statusHari === 'R') {
                    $jamReguler = 7;
                    if ($jamKerja > 7) {
                        $lebih      = $jamKerja - 7;
                        $lembur1_5x = min(1, $lebih);
                        $lembur2x   = max(0, $lebih - 1);
                    }
                    $totalJamHari = ($lembur1_5x * 1.5) + ($lembur2x * 2);
                } elseif ($statusHari === 'P') {
                    $jamReguler = 5;
                    if ($jamKerja > 5) {
                        $lembur1_5x = min(1, $jamKerja - 5);
                        $lembur2x   = max(0, $jamKerja - 6);
                    }
                    $totalJamHari = ($lembur1_5x * 1.5) + ($lembur2x * 2);
                } elseif ($statusHari === 'L') {
                    if ($jamKerja <= 7) {
                        $lembur2x = $jamKerja;
                    } elseif ($jamKerja <= 8) {
                        $lembur2x = 7; $lembur3x = $jamKerja - 7;
                    } else {
                        $lembur2x = 7; $lembur3x = 1; $lembur4x = $jamKerja - 8;
                    }
                    $totalJamHari = ($lembur2x * 2) + ($lembur3x * 3) + ($lembur4x * 4);
                }

                $nominalHari    = round($totalJamHari * $nilaiPerJam, 2);
                $totalJamLembur += $totalJamHari;
                $nominalLembur  += $nominalHari;
            }


            $hariDetails[] = [
                'no'              => $d,
                'hari'            => ['Min','Sen','Sel','Rab','Kam','Jum','Sab'][$date->dayOfWeek],
                'tanggal'         => $date->format('d M Y'),
                'tanggal_fmt'     => $date->format('j-M-y'),
                'jam_kerja'       => $jamKerja,
                'status_hari'     => $statusHari,
                'jam_reguler'     => $jamReguler,
                'lembur_1_5x'     => $lembur1_5x,
                'lembur_2x'       => $lembur2x,
                'lembur_3x'       => $lembur3x,
                'lembur_4x'       => $lembur4x,
                'total_jam_lembur'=> $totalJamHari,
                'nominal_lembur'  => $nominalHari,
                'is_sunday'       => $isSun,
                'is_saturday'     => $isSat,
                'is_holiday'      => $isHol,
                'holiday_label'   => $holidays[$d]?->keterangan ?? null,
            ];
        }

        $lemburBiasa     = 0;
        $totalLemburFlat = ($lSabtu * self::TARIF_SABTU) + ($lLibur * self::TARIF_LIBUR) + ($lemburBiasa * self::TARIF_BIASA);
        $uangHadir       = 0;

        // Hitung TTT aktif saja
        $projectId      = $emp->project_id;
        $activeTttKeys  = $projectId
            ? ProjectTttItem::where('project_id', $projectId)->where('aktif', true)->pluck('key')->toArray()
            : [];
        $activeTttSum   = collect([
            'com_day'            => 0,
            'insentif'           => 0,
            'tunj_makan'         => 0,
            'tunj_produksi'      => 0,
            'tunj_lapangan'      => 0,
            'tunj_kehadiran'     => 0,
            'tunj_pulsa'         => 0,
            'kompensasi_kontrak' => 0,
        ])->only($activeTttKeys)->sum();

        if ($isFlat) {
            $upahLembur = $totalLemburFlat;
            $gajiKotor  = $gajiPokok + $tunjTetap + $kompensasiPwt + $activeTttSum + $totalLemburFlat + $uangHadir;
        } else {
            $upahLembur = round($dul / 173 * $totalJamLembur, 2);
            $gajiKotor  = $gajiPokok + $tunjTetap + $kompensasiPwt + $activeTttSum + $upahLembur;
        }


        $upahPenuh       = $gajiPokok + $tunjTetap;
        $potonganJht     = round($upahPenuh * 0.02);
        $potonganPensiun = round($upahPenuh * 0.01);
        $potonganKes     = round($upahPenuh * 0.01);

        $projectKode  = strtolower($emp->project?->kode ?? '');
        $izinDipotong = !in_array($projectKode, ['khawista', 'purnama']);
        $potonganAlpa = round($upahPenuh / 25 * ($alpa + ($izinDipotong ? $izin : 0)), 2);

        // Potongan khusus Khawista & NK
        $potonganInsentif = 0;
        if ($projectKode === 'khawista') {
            $member = TimesheetMember::where('id_badge', $emp->id_badge)
                ->where('project_id', $emp->project_id)
                ->first();
            $subGroup = strtolower($member?->sub_group ?? '');

            $savedForPot = EmployeePayroll::where([
                'employee_id' => $emp->id,
                'tahun'       => $tahun,
                'bulan'       => $bulan,
            ])->first();

            if ($subGroup === 'construction') {
                $insentifVal = $savedForPot?->insentif ?? 0;
                $potonganInsentif = round($insentifVal / 25 * ($izin + $sakit + $cuti), 2);
            } elseif ($subGroup === 'piling') {
                $tunjLapanganVal = $savedForPot?->tunj_lapangan ?? 0;
                $potonganInsentif = round($tunjLapanganVal / 25 * $izin, 2);
            }
        } elseif ($projectKode === 'nk') {
            $savedForPot = EmployeePayroll::where([
                'employee_id' => $emp->id,
                'tahun'       => $tahun,
                'bulan'       => $bulan,
            ])->first();
            $insentifVal = $savedForPot?->insentif ?? 0;
            $potonganInsentif = round($insentifVal / 25 * ($izin + $sakit + $cuti + $stb), 2);
        }

        $gajiBersih = $gajiKotor - $potonganJht - $potonganPensiun - $potonganKes - $potonganAlpa - $potonganInsentif;

        return [
            'employee_id'         => $emp->id,
            'id_badge'            => $emp->id_badge,
            'nama_lengkap'        => $emp->nama_lengkap,
            'no_ktp'              => $emp->no_ktp,
            'jabatan'             => $emp->position?->nama_jabatan ?? '—',
            'kelompok'            => $isFlat ? 'flat' : 'per_jam',
            'tipe_project'        => 'giam',
            'project_kode'        => strtolower($emp->project?->kode ?? ''),
            'ptkp'                => $emp->ptkp ?? '—',
            'tahun'               => $tahun,
            'bulan'               => $bulan,
            'gaji_pokok'          => $gajiPokok,
            'tunj_tetap'          => $tunjTetap,
            'tunj_jabatan'        => $tunjJabatan,
            'kompensasi_pwt'      => round($kompensasiPwt, 2),
            'upah_penuh'          => $upahPenuh,
            'ttt_perhari'         => 0,
            'com_day'             => 0,
            'insentif'            => (float) ($emp->insentif ?? 0),
            'tunj_makan'          => 0,
            'tunj_produksi'       => 0,
            'tunj_lapangan'       => 0,
            'nilai_lembur_per_jam'=> round($nilaiPerJam, 3),
            'dul'                 => $dul,
            'jml_jam_lembur'      => round($totalJamLembur, 2),
            'upah_lembur'         => round($upahLembur, 2),
            'l_sabtu'             => $lSabtu,
            'l_libur'             => $lLibur,
            'lembur_biasa'        => $lemburBiasa,
            'tarif_sabtu'         => self::TARIF_SABTU,
            'tarif_libur'         => self::TARIF_LIBUR,
            'tarif_biasa'         => self::TARIF_BIASA,
            'total_lembur_flat'   => round($totalLemburFlat, 2),
            'uang_hadir'          => $uangHadir,
            'h_kerja'             => $hKerja,
            'potongan_jht'        => $potonganJht,
            'potongan_pensiun'    => $potonganPensiun,
            'potongan_kes'        => $potonganKes,
            'potongan_alpa'       => $potonganAlpa,
            'potongan_insentif'   => $potonganInsentif,
            'pot_tabung_oksigen'  => 0,
            'kekurangan_bulan_lalu' => 0,
            'gaji_bersih'         => round($gajiBersih, 2),
            'izin'                => $izin,
            'sakit'               => $sakit,
            'alpa'                => $alpa,
            'cuti'                => $cuti,
            'stb'                 => $stb,
            'hari_details'        => $hariDetails,
        ];
    }


    // ════════════════════════════════════════════════════════════
    // CORE: Hitung slip gaji MD / 8 jam
    // ════════════════════════════════════════════════════════════
    private function hitungSlipGajiMd(Employee $emp, int $tahun, int $bulan, array $tsData, array $override = []): array
    {
        $gajiPokok  = (float) ($override['gaji_pokok']   ?? $emp->gaji_pokok   ?? 0);
        $tunjTetap  = (float) ($override['tunj_tetap']   ?? $emp->tunj_jabatan ?? 0);
        $tttPerHari = (float) ($override['ttt_perhari']  ?? 15000);
        $insentif   = (float) ($override['insentif']     ?? 0);
        $tunjMakan  = (float) ($override['tunj_makan']   ?? 15000);
        $comeDay    = (float) ($override['com_day']      ?? 10000);
        $kompensasi = (float) ($override['kompensasi_pwt'] ?? (($gajiPokok + $tunjTetap) / 12));
        $kekurangan = (float) ($override['kekurangan_bulan_lalu'] ?? 0);

        $dul        = $gajiPokok + $tunjTetap;
        $nilaiPerJam = $dul / 173;

        $holidays    = Holiday::inMonth($tahun, $bulan)->get()->keyBy(fn($h) => (int) $h->tanggal->format('j'));
        $daysInMonth = Carbon::create($tahun, $bulan)->daysInMonth;

        $hariDetails    = [];
        $hKerja = $hSabtu = $hMingguLibur = 0;
        $izin = $sakit = $alpa = $cuti = 0;
        $totalOt15 = $totalOt2 = $totalOt3 = $totalOt4 = 0.0;
        $totalJamLembur = $nominalLembur = 0.0;

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $val     = $tsData[$d] ?? null;
            $date    = Carbon::create($tahun, $bulan, $d);
            $isSun   = $date->isSunday();
            $isSat   = $date->isSaturday();
            $isHol   = $holidays->has($d) && !$isSun;
            $isLibur = $isSun || $isHol;

            $statusHari   = $isLibur ? 'L' : ($isSat ? 'P' : 'R');
            $jamKerja     = 0;
            $ot15 = $ot2 = $ot3 = $ot4 = 0;
            $totalJamHari = $nominalHari = 0;

            if ($val !== null && $val !== '') {
                $up = strtoupper((string) $val);
                if      ($up === 'I') { $izin++;  }
                elseif  ($up === 'S') { $sakit++; }
                elseif  ($up === 'A') { $alpa++;  }
                elseif  ($up === 'C') { $cuti++;  }
                elseif  (is_numeric($val)) {
                    $jamKerja = (float) $val;

                    if ($isLibur) {
                        $hMingguLibur++;
                        if ($jamKerja <= 7)      { $ot2 = $jamKerja; }
                        elseif ($jamKerja <= 8)  { $ot2 = 7; $ot3 = $jamKerja - 7; }
                        else                     { $ot2 = 7; $ot3 = 1; $ot4 = $jamKerja - 8; }
                        $totalJamHari = ($ot2 * 2) + ($ot3 * 3) + ($ot4 * 4);
                    } elseif ($isSat) {
                        $hSabtu++;
                        if ($comeDay > 0) {
                            $totalJamHari = 0;
                        } else {
                            if ($jamKerja <= 8) { $ot2 = $jamKerja; }
                            else                { $ot2 = 8; $ot3 = $jamKerja - 8; }
                            $totalJamHari = ($ot2 * 2) + ($ot3 * 3);
                        }
                    } else {
                        $hKerja++;
                        if ($jamKerja > 8) {
                            $lebih = $jamKerja - 8;
                            $ot15  = min(1, $lebih);
                            $ot2   = max(0, $lebih - 1);
                            $totalJamHari = ($ot15 * 1.5) + ($ot2 * 2);
                        }
                    }

                    $nominalHari    = round($totalJamHari * $nilaiPerJam, 2);
                    $totalOt15     += $ot15;
                    $totalOt2      += $ot2;
                    $totalOt3      += $ot3;
                    $totalOt4      += $ot4;
                    $totalJamLembur += $totalJamHari;
                    $nominalLembur  += $nominalHari;
                }
            }

            $hariDetails[] = [
                'no'               => $d,
                'hari'             => ['Min','Sen','Sel','Rab','Kam','Jum','Sab'][$date->dayOfWeek],
                'tanggal'          => $date->format('d M Y'),
                'tanggal_fmt'      => $date->format('j-M-y'),
                'jam_kerja'        => $jamKerja,
                'status_hari'      => $statusHari,
                'jam_reguler'      => ($isLibur || $isSat) ? 0 : min(8, $jamKerja),
                'ot_1_5x'          => $ot15,
                'ot_2x'            => $ot2,
                'ot_3x'            => $ot3,
                'ot_4x'            => $ot4,
                'total_jam_lembur' => $totalJamHari,
                'nominal_lembur'   => $nominalHari,
                'is_sunday'        => $isSun,
                'is_saturday'      => $isSat,
                'is_holiday'       => $isHol,
                'holiday_label'    => $holidays->get($d)?->keterangan ?? null,
            ];
        }

        $hHadir      = $hKerja;
        $hKerja      = $hKerja + $sakit + $cuti;
        $hKerjaAktif = $hHadir + $hSabtu;
        $tttTotal    = $tttPerHari * $hKerjaAktif;
        $upahLembur  = round($nominalLembur, 0);
        $tunjKehadiran = (float) ($override['tunj_kehadiran'] ?? 0);
        $tunjPulsa     = (float) ($override['tunj_pulsa']     ?? 0);
        $comDayTotal   = round($comeDay * $hSabtu, 2);
        $uKerja        = round(($tunjMakan + $tunjKehadiran) * $hKerjaAktif, 2);
        $uBasic        = round(($gajiPokok + $tunjTetap) / 17 * min($hKerja, 17), 2);
        $gajiKotor     = $uBasic + $kompensasi
            + $uKerja
            + $comDayTotal
            + $upahLembur
            + $tunjPulsa
            + $kekurangan;

        $upahPenuh       = $dul;
        $potonganJht     = round($upahPenuh * 0.02);
        $potonganPensiun = round($upahPenuh * 0.01);
        $potonganKes     = round($upahPenuh * 0.01);
        $potonganAlpa    = $alpa > 0 ? round($upahPenuh / 25 * $alpa) : 0;
        $gajiBersih      = $gajiKotor - $potonganJht - $potonganPensiun - $potonganKes - $potonganAlpa;

        return [
            'employee_id'          => $emp->id,
            'id_badge'             => $emp->id_badge,
            'nama_lengkap'         => $emp->nama_lengkap,
            'no_ktp'               => $emp->no_ktp,
            'jabatan'              => $emp->position?->nama_jabatan ?? '—',
            'kelompok'             => 'per_jam',
            'tipe_project'         => 'md',
            'project_kode'         => strtolower($emp->project?->kode ?? ''),
            'ptkp'                 => $emp->ptkp ?? '—',
            'tahun'                => $tahun,
            'bulan'                => $bulan,
            'gaji_pokok'           => $gajiPokok,
            'tunj_tetap'           => $tunjTetap,
            'kompensasi_pwt'       => round($kompensasi, 2),
            'upah_penuh'           => $upahPenuh,
            'dul'                  => $dul,
            'ttt_perhari'          => $tttPerHari,
            'ttt_total'            => round($tttTotal, 2),
            'insentif'             => $insentif,
            'tunj_makan'           => $tunjMakan,
            'tunj_makan_total'     => round($tunjMakan * $hKerjaAktif, 2),
            'com_day'              => $comeDay,
            'com_day_total'        => $comDayTotal,
            'tunj_produksi'        => 0,
            'tunj_lapangan'        => 0,
            'nilai_lembur_per_jam' => round($nilaiPerJam, 3),
            'total_ot_15x'         => round($totalOt15, 2),
            'total_ot_2x'          => round($totalOt2, 2),
            'total_ot_3x'          => round($totalOt3, 2),
            'total_ot_4x'          => round($totalOt4, 2),
            'jml_jam_lembur'       => round($totalJamLembur, 2),
            'upah_lembur'          => $upahLembur,
            'l_sabtu'              => $hSabtu,
            'l_libur'              => $hMingguLibur,
            'lembur_biasa'         => 0,
            'tarif_sabtu'          => 0,
            'tarif_libur'          => 0,
            'tarif_biasa'          => 0,
            'total_lembur_flat'    => 0,
            'uang_hadir'           => 0,
            'h_kerja'              => $hKerjaAktif,
            'h_sabtu'              => $hSabtu,
            'h_minggu_libur'       => $hMingguLibur,
            'h_basic'              => $hKerja,
            'u_basic'              => $uBasic,
            'u_kerja'              => $uKerja,
            'tunj_kehadiran'       => $tunjKehadiran,
            'tunj_pulsa'           => $tunjPulsa,
            'gaji_kotor'           => round($gajiKotor, 2),
            'potongan_jht'         => $potonganJht,
            'potongan_pensiun'     => $potonganPensiun,
            'potongan_kes'         => $potonganKes,
            'potongan_alpa'        => $potonganAlpa,
            'potongan_insentif'    => 0,
            'pot_tabung_oksigen'   => 0,
            'kekurangan_bulan_lalu'=> $kekurangan,
            'gaji_bersih'          => round($gajiBersih, 2),
            'izin'                 => $izin,
            'sakit'                => $sakit,
            'alpa'                 => $alpa,
            'cuti'                 => $cuti,
            'hari_details'         => $hariDetails,
        ];
    }


    // ════════════════════════════════════════════════════════════
    // HELPERS
    // ════════════════════════════════════════════════════════════
    private function getTimesheetData(int $employeeId, int $tahun, int $bulan): array
    {
        return Timesheet::where([
            'employee_id' => $employeeId,
            'tahun'       => $tahun,
            'bulan'       => $bulan,
        ])->get()->keyBy('hari')->map(fn($t) => $t->nilai)->toArray();
    }

    private function getTttItems(int $projectId): \Illuminate\Support\Collection
    {
        $items = ProjectTttItem::where('project_id', $projectId)
            ->orderBy('urutan')
            ->get(['id', 'key', 'label', 'is_default', 'aktif']);

        if ($items->isEmpty()) {
            $defaults = [
                ['key' => 'tunj_makan',         'label' => 'Tunj Makan',     'urutan' => 1, 'aktif' => true,  'is_default' => true],
                ['key' => 'tunj_produksi',       'label' => 'Tunj Produksi',  'urutan' => 2, 'aktif' => true,  'is_default' => true],
                ['key' => 'tunj_lapangan',       'label' => 'Tunj Lapangan',  'urutan' => 3, 'aktif' => true,  'is_default' => true],
                ['key' => 'tunj_kehadiran',      'label' => 'Tunj Kehadiran', 'urutan' => 4, 'aktif' => false, 'is_default' => true],
                ['key' => 'tunj_pulsa',          'label' => 'Tunj Pulsa',     'urutan' => 5, 'aktif' => false, 'is_default' => true],
                ['key' => 'kompensasi_kontrak',  'label' => 'Komp. Kontrak',  'urutan' => 6, 'aktif' => false, 'is_default' => true],
                ['key' => 'insentif',            'label' => 'Insentif',       'urutan' => 7, 'aktif' => false, 'is_default' => true],
                ['key' => 'com_day',             'label' => 'Com Day',        'urutan' => 8, 'aktif' => false, 'is_default' => true],
                ['key' => 'kompensasi_pwt',      'label' => 'Komp. PWT',      'urutan' => 9, 'aktif' => true,  'is_default' => true],
            ];

            foreach ($defaults as $d) {
                ProjectTttItem::create(array_merge($d, ['project_id' => $projectId]));
            }

            $items = ProjectTttItem::where('project_id', $projectId)
                ->orderBy('urutan')
                ->get(['id', 'key', 'label', 'is_default', 'aktif']);
        }

        return $items;
    }

    private function bulanNama(): array
    {
        return [
            1 => 'Januari',  2 => 'Februari', 3 => 'Maret',    4 => 'April',
            5 => 'Mei',      6 => 'Juni',      7 => 'Juli',     8 => 'Agustus',
            9 => 'September',10 => 'Oktober', 11 => 'November',12 => 'Desember',
        ];
    }


    public function hitungSlipGajiPublic(Employee $emp, int $tahun, int $bulan, bool $isFlat = false): array
    {
        return $this->hitungSlipGaji($emp, $tahun, $bulan, $isFlat);
    }

    public function hitungSlipGajiMdPublic(Employee $emp, int $tahun, int $bulan, array $tsData, array $override = []): array
    {
        return $this->hitungSlipGajiMd($emp, $tahun, $bulan, $tsData, $override);
    }

    public function getTimesheetDataPublic(int $employeeId, int $tahun, int $bulan): array
    {
        return $this->getTimesheetData($employeeId, $tahun, $bulan);
    }
}