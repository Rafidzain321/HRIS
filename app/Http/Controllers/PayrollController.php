<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\EmployeeHoDetail;
use App\Models\EmployeePayroll;
use App\Models\EmployeeSalaryHistory;
use App\Models\Holiday;
use App\Models\Project;
use App\Models\Timesheet;
use App\Models\TimesheetMember;
use App\Models\ProjectTttItem;
use App\Models\ProjectPotonganItem;
use App\Models\ProjectBpjsConfig;
use App\Models\ProjectTtdConfig;
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

        $members = $this->getPayrollRoster($projectId, $tahun, $bulan);

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
                $slipData    = $this->getSlipData((int) $employeeId, $tahun, $bulan);
                $hariDetails = $slipData['hari_details'] ?? [];
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
        $isHo      = $projectId && Project::find($projectId)?->tipe_gaji === 'ho';

        $built = $this->buildPayrollRows($tahun, $bulan, $projectId);

        // Simpan snapshot lembur/BPJS/potongan terbaru ke database setiap halaman ini
        // dibuka, supaya data yang dibaca langsung dari tabel tersimpan (Dashboard, cetak
        // slip dari riwayat) selalu ikut fresh tanpa perlu tombol "Hitung Ulang" manual.
        if (!$this->isViewer()) {
            $this->persistPayrollSnapshot($tahun, $bulan, $projectId);
        }

        return Inertia::render('Timesheet/DataGaji', [
            'tahun'             => $tahun,
            'bulan'             => $bulan,
            'bulan_nama'        => $bulanNama[$bulan],
            'bulan_list'        => $bulanNama,
            'rows'              => $built['rows'],
            'total_gaji_kotor'  => $built['total_gaji_kotor'],
            'total_gaji_bersih' => $built['total_gaji_bersih'],
            'ttt_items'         => $built['ttt_items'],
            'potongan_items'    => $built['potongan_items'],
            'bpjs_pct'          => $projectId ? $this->getBpjsPct($projectId, $tahun, $bulan) : ['jht' => 2.0, 'pensiun' => 1.0, 'kes' => 1.0],
            'ttd_list'          => $projectId ? $this->getTtdList($projectId) : null,
            'project_info'      => $projectId
                ? Project::find($projectId, ['id', 'kode', 'nama', 'tipe_gaji', 'tipe_timesheet'])
                : null,
            'salary_matrix'     => $isHo ? $this->buildHoSalaryMatrix($projectId, $tahun, $bulan) : null,
        ]);
    }

    // ════════════════════════════════════════════════════════════
    // MATRIKS RIWAYAT GAJI HO — kolom lebar ala Excel sumbernya (satu kolom per
    // label historis 2018-2026), digabung untuk seluruh karyawan HO-1 & HO-2 dalam
    // satu set kolom (union label dari kedua unit). Label diurutkan kronologis
    // berdasarkan tahun/bulan hasil parsing; label tanpa tanggal (mis. "PENYESUAIAN",
    // "KENAIKAN") disisipkan berdasarkan posisi kolom aslinya di spreadsheet ($urutan).
    // Selain arsip Excel, kolom juga otomatis bertambah dari periode EmployeePayroll
    // yang sudah pernah diisi lewat kolom "GAJI {BULAN} {TAHUN}" berjalan (di luar
    // periode yang sedang aktif), supaya tabel ini terus bertambah tiap bulan/tahun
    // berjalan tanpa perlu import manual lagi.
    private function buildHoSalaryMatrix(int $projectId, int $currentTahun, int $currentBulan): array
    {
        $bulanNama = $this->bulanNama();

        $employeeIds = EmployeeHoDetail::whereIn('unit', ['HO-1', 'HO-2'])
            ->whereIn('employee_id', Employee::where('project_id', $projectId)->pluck('id'))
            ->pluck('employee_id');

        if ($employeeIds->isEmpty()) {
            return ['labels' => [], 'values' => [], 'periods' => []];
        }

        $history = EmployeeSalaryHistory::whereIn('employee_id', $employeeIds)->get();

        $labelInfo = [];
        foreach ($history->groupBy('label') as $label => $rows) {
            $labelInfo[$label] = [
                'urutan' => $rows->avg('urutan'),
                'tahun'  => $rows->pluck('tahun')->filter()->first(),
                'bulan'  => $rows->pluck('bulan')->filter()->first(),
            ];
        }

        // Periode EmployeePayroll di luar periode yang sedang dibuka & sudah diisi.
        $payrolls = EmployeePayroll::whereIn('employee_id', $employeeIds)
            ->where(fn ($q) => $q->where('tahun', '!=', $currentTahun)->orWhere('bulan', '!=', $currentBulan))
            ->where('gaji_pokok', '>', 0)
            ->get();

        $periods = [];
        $payrollValues = [];
        foreach ($payrolls as $p) {
            $label = 'GAJI ' . strtoupper($bulanNama[$p->bulan]) . ' ' . $p->tahun;
            if (!isset($labelInfo[$label])) {
                $labelInfo[$label] = ['urutan' => null, 'tahun' => $p->tahun, 'bulan' => $p->bulan];
                $periods[$label]   = ['tahun' => $p->tahun, 'bulan' => $p->bulan];
            }
            $payrollValues[$p->employee_id][$label] = (float) $p->gaji_pokok;
        }

        // Urutan kronologis: label dengan tahun pasti diurutkan langsung; label
        // tanpa tahun diinterpolasi di antara label bertanggal terdekat (berdasarkan
        // kedekatan urutan kolom aslinya di Excel).
        $dated = [];
        foreach ($labelInfo as $label => $info) {
            if ($info['tahun']) {
                $dated[$label] = ((int) $info['tahun']) * 12 + ((int) ($info['bulan'] ?: 1));
            }
        }
        $datedByUrutan = [];
        foreach ($dated as $label => $key) {
            if ($labelInfo[$label]['urutan'] !== null) {
                $datedByUrutan[] = ['urutan' => $labelInfo[$label]['urutan'], 'key' => $key];
            }
        }
        usort($datedByUrutan, fn ($a, $b) => $a['urutan'] <=> $b['urutan']);

        $finalKey = [];
        foreach ($labelInfo as $label => $info) {
            if (isset($dated[$label])) {
                $finalKey[$label] = $dated[$label];
                continue;
            }
            $u = $info['urutan'];
            if ($u === null || empty($datedByUrutan)) {
                $finalKey[$label] = PHP_INT_MAX;
                continue;
            }
            $before = null;
            $after  = null;
            foreach ($datedByUrutan as $d) {
                if ($d['urutan'] <= $u) $before = $d;
                if ($d['urutan'] >= $u && $after === null) $after = $d;
            }
            if ($before && $after) {
                $finalKey[$label] = ($before['key'] + $after['key']) / 2;
            } elseif ($before) {
                $finalKey[$label] = $before['key'] + 0.5;
            } elseif ($after) {
                $finalKey[$label] = $after['key'] - 0.5;
            } else {
                $finalKey[$label] = PHP_INT_MAX;
            }
        }
        asort($finalKey);
        $labels = array_keys($finalKey);

        $values = [];
        foreach ($history as $h) {
            $values[$h->employee_id][$h->label] = (float) $h->nominal;
        }
        foreach ($payrollValues as $empId => $labelVals) {
            foreach ($labelVals as $label => $val) {
                $values[$empId][$label] = $val;
            }
        }

        return ['labels' => $labels, 'values' => $values, 'periods' => $periods];
    }


    // ════════════════════════════════════════════════════════════
    // BUILD ROWS — dipakai bersama oleh dataGaji() dan export Excel
    // ════════════════════════════════════════════════════════════
    public function buildPayrollRows(int $tahun, int $bulan, ?int $projectId): array
    {
        $members = $this->getPayrollRoster($projectId, $tahun, $bulan);

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

        $tttItems      = $projectId ? $this->getTttItems($projectId) : collect();
        $customKeys    = $tttItems->where('is_default', false)->pluck('key')->toArray();
        $potonganItems = $projectId ? $this->getPotonganItems($projectId) : collect();
        $potonganKeys  = $potonganItems->pluck('key')->toArray();

        // Cache persentase BPJS per project_id — dipakai per baris di bawah supaya tidak query
        // berulang, tapi tetap benar kalau roster mencakup lebih dari 1 project (mis. tampilan "semua project").
        $bpjsPctCache = [];
        $resolveBpjsPct = function (?int $pid) use (&$bpjsPctCache, $tahun, $bulan) {
            if (!$pid) return ['jht' => 2.0, 'pensiun' => 1.0, 'kes' => 1.0];
            if (!isset($bpjsPctCache[$pid])) {
                $bpjsPctCache[$pid] = $this->getBpjsPct($pid, $tahun, $bulan);
            }
            return $bpjsPctCache[$pid];
        };

        $rows            = [];
        $totalGajiKotor  = 0;
        $totalGajiBersih = 0;

        foreach ($members as $m) {
            $emp = $m->employee;
            if (!$emp) continue;

            $isMd   = $emp->project?->tipe_gaji === 'md';
            $isHo   = $emp->project?->tipe_gaji === 'ho';
            $isFlat = $m->kelompok === 'flat';

            $saved = $savedPayrolls->get($emp->id);

            if ($isHo) {
                $slip = $this->hitungSlipGajiHo($emp, $tahun, $bulan);
            } elseif ($isMd) {
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
                $slip['l_sabtu']            = $saved->l_sabtu ?? $slip['l_sabtu'];
                $slip['l_libur']            = $saved->l_libur ?? $slip['l_libur'];
                $slip['total_lembur_flat']  = $saved->total_lembur_flat ?? $slip['total_lembur_flat'];
                $slip['upah_lembur'] = $saved->upah_lembur ?? $slip['upah_lembur'];
                $slip['jml_jam_lembur']     = $saved->jml_jam_lembur ?? $slip['jml_jam_lembur'];
                $slip['kekurangan_bulan_lalu'] = $saved->kekurangan_bulan_lalu;
                $slip['tunj_kehadiran']     = $saved->tunj_kehadiran ?? 0;
                $slip['tunj_pulsa']         = $saved->tunj_pulsa    ?? 0;
                $slip['kompensasi_kontrak'] = $saved->kompensasi_kontrak ?? 0;
                $slip['kompensasi_pwt']     = $saved->kompensasi_pwt ?: $slip['kompensasi_pwt'];
                $slip['tunj_jabatan']       = $saved->tunj_jabatan ?? 0;
                $slip['id']                 = $saved->id;
                $slip['pot_tabung_oksigen'] = $saved->pot_tabung_oksigen ?? 0;
                $slip['insentif'] = $saved->insentif ?? $slip['insentif'];
                $slip['stb']                   = $saved->stb ?? $slip['stb'] ?? 0;
                $slip['catatan']               = $saved->catatan ?? null;
                $slip['dibuat_oleh']           = $saved->dibuat_oleh ?? null;

                if ($isMd) {
                    $slip['ttt_perhari'] = $saved->ttt_perhari ?: $slip['ttt_perhari'];
                    $slip['com_day']     = $saved->com_day     ?: $slip['com_day'];
                }

                // TTT custom (per-project, dinamis) — harus masuk ke gaji_kotor juga,
                // bukan cuma ditampilkan sebagai kolom terpisah.
                $customSum = 0;
                foreach ($customKeys as $key) {
                    $val = $saved->ttt_custom[$key] ?? 0;
                    $slip[$key] = $val;
                    $customSum += (float) $val;
                }
                $slip['custom_ttt_sum'] = $customSum;

                // Potongan dinamis (khusus HO) — dihitung & ditampilkan saja, tidak dikurangkan
                // dari gaji_bersih (lihat hitungGajiKotorBersih cabang $isHo).
                $potonganCustomSum = 0;
                foreach ($potonganKeys as $key) {
                    $val = $saved->potongan_custom[$key] ?? 0;
                    $slip['pot_custom_' . $key] = $val;
                    $potonganCustomSum += (float) $val;
                }
                $slip['potongan_custom_sum'] = $potonganCustomSum;

                // Recompute gaji_kotor/gaji_bersih/BPJS dari nilai yang sudah di-override —
                // dipakai satu rumus yang sama untuk semua project (bukan cuma MD/NK).
                $bpjsPct = $resolveBpjsPct($emp->project_id);
                $slip = $this->hitungGajiKotorBersih($slip, $isMd, $isHo, $bpjsPct['jht'], $bpjsPct['pensiun'], $bpjsPct['kes']);
            } else {
                foreach ($customKeys as $key) {
                    $slip[$key] = 0;
                }
                foreach ($potonganKeys as $key) {
                    $slip['pot_custom_' . $key] = 0;
                }
                $slip['potongan_custom_sum'] = 0;
            }

            $slip['sub_group']    = $m->sub_group;
            $slip['urutan']       = $m->urutan ?? 999;
            $slip['project_kode'] = strtolower($emp->project?->kode ?? '');
            // nama_bank/no_rekening/ptkp: data induk karyawan (kolom employees), sama seperti
            // halaman Edit Karyawan — bukan snapshot per periode, supaya editnya nyambung ke sana.
            $slip['nama_bank']    = $emp->nama_bank    ?? null;
            $slip['no_rekening']  = $emp->no_rekening  ?? null;
            $slip['no_bpjs_tk']   = $saved->no_bpjs_tk   ?? $emp->no_bpjs_tk   ?? null;
            $slip['no_bpjs_kes']  = $saved->no_bpjs_kes  ?? $emp->no_bpjs_kes  ?? null;
            $slip['ptkp']         = $emp->ptkp ?? '—';
            $rows[]             = $slip;
            $totalGajiKotor    += $slip['gaji_kotor']  ?? 0;
            $totalGajiBersih   += $slip['gaji_bersih'] ?? 0;
        }

        return [
            'rows'              => $rows,
            'total_gaji_kotor'  => $totalGajiKotor,
            'total_gaji_bersih' => $totalGajiBersih,
            'ttt_items'         => $tttItems,
            'potongan_items'    => $potonganItems,
        ];
    }

    // ════════════════════════════════════════════════════════════
    // SIMPAN DATA GAJI (batch semua member)
    // ════════════════════════════════════════════════════════════
    // ════════════════════════════════════════════════════════════
    // PERSIST SNAPSHOT — hitung ulang lembur/BPJS/potongan seluruh
    // karyawan & simpan ke employee_payroll. Dipanggil otomatis tiap
    // halaman Data Gaji dibuka (lihat dataGaji()), supaya tabel tersimpan
    // (dipakai Dashboard, cetak slip dari riwayat, dll) selalu up-to-date
    // tanpa perlu tombol manual. Nilai yang sudah diisi manual (Gaji Pokok,
    // Tunjangan, TTT) tidak ditimpa.
    // ════════════════════════════════════════════════════════════
    private function persistPayrollSnapshot(int $tahun, int $bulan, ?int $projectId): void
    {
        $members = $this->getPayrollRoster($projectId, $tahun, $bulan);

        $bpjsPctCache = [];
        $resolveBpjsPct = function (?int $pid) use (&$bpjsPctCache, $tahun, $bulan) {
            if (!$pid) return ['jht' => 2.0, 'pensiun' => 1.0, 'kes' => 1.0];
            if (!isset($bpjsPctCache[$pid])) {
                $bpjsPctCache[$pid] = $this->getBpjsPct($pid, $tahun, $bulan);
            }
            return $bpjsPctCache[$pid];
        };

        foreach ($members as $m) {
            $emp = $m->employee;
            if (!$emp) continue;

            $isMd   = $emp->project?->tipe_gaji === 'md';
            $isHo   = $emp->project?->tipe_gaji === 'ho';
            $isFlat = $m->kelompok === 'flat';
            $bpjsPct = $resolveBpjsPct($emp->project_id);

            // Ambil data yang sudah ada (manual) — supaya field manual tidak ditimpa
            $existing = EmployeePayroll::where([
                'employee_id' => $emp->id,
                'tahun'       => $tahun,
                'bulan'       => $bulan,
            ])->first();

            if ($isHo) {
                // HO tidak punya timesheet — "Hitung Ulang" cuma refresh gaji_kotor/gaji_bersih
                // dari nilai yang sudah tersimpan (gaji_pokok/tunj_tetap/TTT custom/izin-sakit-alpa-cuti manual).
                $slip = $this->hitungSlipGajiHo($emp, $tahun, $bulan);

                $existingTtt      = $existing?->ttt_custom ?? [];
                $existingPotongan = $existing?->potongan_custom ?? [];

                $customKeys = $emp->project_id ? $this->getTttItems($emp->project_id)->where('is_default', false)->pluck('key')->toArray() : [];
                $customSum  = 0;
                foreach ($customKeys as $key) {
                    $slip[$key] = $existingTtt[$key] ?? 0;
                    $customSum += (float) $slip[$key];
                }
                $slip['custom_ttt_sum'] = $customSum;

                $potonganKeys = $emp->project_id ? $this->getPotonganItems($emp->project_id)->pluck('key')->toArray() : [];
                $potonganCustomSum = 0;
                foreach ($potonganKeys as $key) {
                    $slip['pot_custom_' . $key] = $existingPotongan[$key] ?? 0;
                    $potonganCustomSum += (float) $slip['pot_custom_' . $key];
                }
                $slip['potongan_custom_sum'] = $potonganCustomSum;

                $slip = $this->hitungGajiKotorBersih($slip, false, true, $bpjsPct['jht'], $bpjsPct['pensiun'], $bpjsPct['kes']);
            } elseif ($isMd) {
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
                    $slip['gaji_pokok']     = $existing->gaji_pokok      ?? $slip['gaji_pokok'];
                    $slip['tunj_tetap']     = $existing->tunj_tetap      ?? $slip['tunj_tetap'];
                    $slip['tunj_makan']     = $existing->tunj_makan      ?? $slip['tunj_makan'];
                    $slip['tunj_produksi']  = $existing->tunj_produksi   ?? $slip['tunj_produksi'];
                    $slip['tunj_lapangan']  = $existing->tunj_lapangan   ?? $slip['tunj_lapangan'];
                    $slip['insentif']       = $existing->insentif        ?? $slip['insentif'];
                    $slip['com_day']        = $existing->com_day         ?? $slip['com_day'];
                    $slip['uang_hadir']     = $existing->uang_hadir      ?? $slip['uang_hadir'];
                    $slip['lembur_biasa']   = $existing->lembur_biasa    ?? $slip['lembur_biasa'];
                    $slip['l_sabtu']        = $existing->l_sabtu         ?? $slip['l_sabtu'];
                    $slip['l_libur']        = $existing->l_libur         ?? $slip['l_libur'];
                    $slip['kekurangan_bulan_lalu'] = $existing->kekurangan_bulan_lalu ?? 0;
                    $slip['tunj_kehadiran']     = $existing->tunj_kehadiran     ?? 0;
                    $slip['tunj_pulsa']         = $existing->tunj_pulsa         ?? 0;
                    $slip['kompensasi_kontrak'] = $existing->kompensasi_kontrak ?? 0;

                    $upahPenuh   = $slip['gaji_pokok'] + $slip['tunj_tetap'];
                    $nilaiPerJam = $upahPenuh / 173;

                    $projectKode = strtolower($emp->project?->kode ?? '');
                    $isKhawistaPilingFlat = $isFlat
                        && in_array($projectKode, ['khawista', 'nk'])
                        && strtolower($m->sub_group ?? '') === 'piling';
                    // GIAM: lembur flat diimpor sebagai lump sum dari Excel sumber (tidak ada rincian
                    // L Sabtu/L Libur/Lembur Biasa per hari), jadi jangan dihitung ulang dari tarif standar.
                    $isGiamLumpSumFlat = $isFlat && $projectKode === 'giam';

                    if ($isKhawistaPilingFlat || $isGiamLumpSumFlat) {
                        // Piling & GIAM pakai lump_sum — jangan recalc dari tarif standar
                        $slip['total_lembur_flat'] = $existing->total_lembur_flat ?? 0;
                        $slip['upah_lembur']       = $existing->upah_lembur       ?? 0;
                    } elseif ($isFlat) {
                        $slip['total_lembur_flat'] = ($slip['l_sabtu'] * self::TARIF_SABTU)
                            + ($slip['l_libur'] * self::TARIF_LIBUR)
                            + ($slip['lembur_biasa'] * self::TARIF_BIASA);
                        $slip['upah_lembur'] = $slip['total_lembur_flat'];
                    } else {
                        // Per jam: tetap pakai jam lembur HASIL HITUNG ULANG dari timesheet terbaru
                        $slip['upah_lembur'] = round($nilaiPerJam * $slip['jml_jam_lembur'], 2);
                    }

                    $izinDipotong = !in_array($projectKode, ['khawista', 'purnama']);
                    $slip['potongan_alpa'] = round($upahPenuh / 25 * ($slip['alpa'] + ($izinDipotong ? $slip['izin'] : 0)), 2);

                    $potonganInsentif = 0;
                    if ($projectKode === 'khawista') {
                        $subGroup = strtolower($m->sub_group ?? '');
                        if ($subGroup === 'construction') {
                            $potonganInsentif = round($slip['insentif'] / 25 * ($slip['izin'] + $slip['sakit'] + $slip['cuti']), 2);
                        } elseif ($subGroup === 'piling') {
                            $potonganInsentif = round($slip['tunj_lapangan'] / 25 * $slip['izin'], 2);
                        }
                    } elseif ($projectKode === 'nk') {
                        $stbVal = $existing->stb ?? $slip['stb'] ?? 0;
                        $potonganInsentif = round($slip['insentif'] / 25 * ($slip['izin'] + $slip['sakit'] + $slip['cuti'] + $stbVal), 2);
                    }
                    $slip['potongan_insentif']  = $potonganInsentif;
                    $slip['pot_tabung_oksigen'] = $existing->pot_tabung_oksigen ?? 0;

                    // TTT custom (per-project, dinamis) — harus masuk ke gaji_kotor juga.
                    $pidForTtt  = $emp->project_id ?? $projectId;
                    $customKeys = $pidForTtt ? $this->getTttItems($pidForTtt)->where('is_default', false)->pluck('key')->toArray() : [];
                    $customSum  = 0;
                    foreach ($customKeys as $key) {
                        $slip[$key] = $existing->ttt_custom[$key] ?? 0;
                        $customSum += (float) $slip[$key];
                    }
                    $slip['custom_ttt_sum'] = $customSum;

                    // Satu sumber perhitungan yang sama dengan Data Gaji & Slip Gaji
                    $slip = $this->hitungGajiKotorBersih($slip, false, false, $bpjsPct['jht'], $bpjsPct['pensiun'], $bpjsPct['kes']);
                }
            }

            EmployeePayroll::updateOrCreate(
                ['employee_id' => $emp->id, 'tahun' => $tahun, 'bulan' => $bulan],
                [
                    'gaji_pokok'            => $slip['gaji_pokok'],
                    'tunj_tetap'            => $slip['tunj_tetap'],
                    'tunj_jabatan'          => $existing?->tunj_jabatan ?? $slip['tunj_jabatan'] ?? 0,
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
                    'stb'                   => $existing?->stb ?? $slip['stb'] ?? 0,
                    'tunj_kehadiran'        => $existing?->tunj_kehadiran  ?? 0,
                    'tunj_pulsa'            => $existing?->tunj_pulsa      ?? 0,
                    'ttt_custom'            => $existing?->ttt_custom      ?? null,
                    'potongan_custom'       => $existing?->potongan_custom ?? null,
                    'dibuat_oleh'           => auth()->user()?->name ?? 'System',
                ]
            );
        }
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
            'no_bpjs_tk'             => 'nullable|string|max:50',
            'no_bpjs_kes'            => 'nullable|string|max:50',
            'catatan'                => 'nullable|string|max:500',
            'tunj_kehadiran'         => 'nullable|numeric|min:0',
            'tunj_pulsa'             => 'nullable|numeric|min:0',
            'kompensasi_kontrak'     => 'nullable|numeric|min:0',
            'kompensasi_pwt'         => 'nullable|numeric|min:0',
            'ttt_custom'             => 'nullable|array',
            'ttt_custom.*'           => 'nullable|numeric|min:0',
            'potongan_custom'        => 'nullable|array',
            'potongan_custom.*'      => 'nullable|numeric|min:0',
            'izin'                   => 'nullable|integer|min:0',
            'sakit'                  => 'nullable|integer|min:0',
            'alpa'                   => 'nullable|integer|min:0',
            'cuti'                   => 'nullable|integer|min:0',
        ]);

        $tahun = $data['tahun'];
        $bulan = $data['bulan'];
        $emp   = Employee::with(['position', 'project'])->findOrFail($employeeId);
        $isMd  = $emp->project?->tipe_gaji === 'md';
        $isHo  = $emp->project?->tipe_gaji === 'ho';
        $bpjsPct = $emp->project_id
            ? $this->getBpjsPct($emp->project_id, $data['tahun'], $data['bulan'])
            : ['jht' => 2.0, 'pensiun' => 1.0, 'kes' => 1.0];

        $payroll = EmployeePayroll::firstOrNew([
            'employee_id' => $employeeId,
            'tahun'       => $tahun,
            'bulan'       => $bulan,
        ]);

        // Merge ttt_custom dengan yang sudah ada, tidak overwrite semua
        $mergedTttCustom = $payroll->ttt_custom ?? [];
        if (!empty($data['ttt_custom'])) {
            $mergedTttCustom = array_merge($mergedTttCustom, $data['ttt_custom']);
        }

        // Merge potongan_custom dengan yang sudah ada, tidak overwrite semua
        $mergedPotonganCustom = $payroll->potongan_custom ?? [];
        if (!empty($data['potongan_custom'])) {
            $mergedPotonganCustom = array_merge($mergedPotonganCustom, $data['potongan_custom']);
        }

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

        } elseif ($isHo) {
            $slip = $this->hitungSlipGajiHo($emp, $tahun, $bulan);

            $slip['gaji_pokok']     = isset($data['gaji_pokok']) ? (float) $data['gaji_pokok'] : $slip['gaji_pokok'];
            $slip['tunj_tetap']     = isset($data['tunj_tetap']) ? (float) $data['tunj_tetap'] : $slip['tunj_tetap'];
            $slip['kompensasi_pwt'] = isset($data['kompensasi_pwt'])
                ? (float) $data['kompensasi_pwt']
                : round(($slip['gaji_pokok'] + $slip['tunj_tetap']) / 12, 2);
            $slip['kekurangan_bulan_lalu'] = $data['kekurangan_bulan_lalu'] ?? 0;
            $slip['izin']  = isset($data['izin'])  ? (int) $data['izin']  : $slip['izin'];
            $slip['sakit'] = isset($data['sakit']) ? (int) $data['sakit'] : $slip['sakit'];
            $slip['alpa']  = isset($data['alpa'])  ? (int) $data['alpa']  : $slip['alpa'];
            $slip['cuti']  = isset($data['cuti'])  ? (int) $data['cuti']  : $slip['cuti'];

            $customKeys = $emp->project_id ? $this->getTttItems($emp->project_id)->where('is_default', false)->pluck('key')->toArray() : [];
            $customSum  = 0;
            foreach ($customKeys as $key) {
                $slip[$key] = $mergedTttCustom[$key] ?? 0;
                $customSum += (float) $slip[$key];
            }
            $slip['custom_ttt_sum'] = $customSum;

            $potonganKeys = $emp->project_id ? $this->getPotonganItems($emp->project_id)->pluck('key')->toArray() : [];
            $potonganCustomSum = 0;
            foreach ($potonganKeys as $key) {
                $slip['pot_custom_' . $key] = $mergedPotonganCustom[$key] ?? 0;
                $potonganCustomSum += (float) $slip['pot_custom_' . $key];
            }
            $slip['potongan_custom_sum'] = $potonganCustomSum;

            $slip = $this->hitungGajiKotorBersih($slip, false, true, $bpjsPct['jht'], $bpjsPct['pensiun'], $bpjsPct['kes']);

        } else {
            // Baca kelompok dari TimesheetMember
            $member = TimesheetMember::where('id_badge', $emp->id_badge)
                ->where('project_id', $this->activeProjectId())
                ->first();
            $isFlat = $member?->kelompok === 'flat';

            $slip = $this->hitungSlipGaji($emp, $tahun, $bulan, $isFlat);

            $slip['gaji_pokok']  = isset($data['gaji_pokok']) ? (float) $data['gaji_pokok'] : $slip['gaji_pokok'];
            $slip['tunj_tetap']  = isset($data['tunj_tetap']) ? (float) $data['tunj_tetap'] : $slip['tunj_tetap'];
            $slip['lembur_biasa'] = $data['lembur_biasa'] ?? $slip['lembur_biasa'];
            $slip['l_sabtu']     = isset($data['l_sabtu']) ? (int) $data['l_sabtu'] : $slip['l_sabtu'];
            $slip['l_libur']     = isset($data['l_libur']) ? (int) $data['l_libur'] : $slip['l_libur'];
            $slip['total_lembur_flat'] = ($slip['l_sabtu'] * self::TARIF_SABTU)
                + ($slip['l_libur'] * self::TARIF_LIBUR) + ($slip['lembur_biasa'] * self::TARIF_BIASA);
            $slip['tunj_makan']     = $data['tunj_makan']    ?? $slip['tunj_makan'];
            $slip['tunj_produksi']  = $data['tunj_produksi'] ?? $slip['tunj_produksi'];
            $slip['tunj_lapangan']  = $data['tunj_lapangan'] ?? $slip['tunj_lapangan'];
            $slip['insentif']       = $data['insentif']      ?? $slip['insentif'];
            $slip['com_day']        = $data['com_day']       ?? $slip['com_day'];
            $slip['uang_hadir']     = $data['uang_hadir']    ?? $slip['uang_hadir'];
            $slip['kekurangan_bulan_lalu'] = $data['kekurangan_bulan_lalu'] ?? 0;
            $slip['tunj_kehadiran']     = $data['tunj_kehadiran'] ?? ($payroll->tunj_kehadiran ?? 0);
            $slip['tunj_pulsa']         = $data['tunj_pulsa']     ?? ($payroll->tunj_pulsa ?? 0);
            $slip['kompensasi_kontrak'] = $payroll->kompensasi_kontrak ?? 0;

            $upahPenuh   = $slip['gaji_pokok'] + $slip['tunj_tetap'];
            $nilaiPerJam = $upahPenuh / 173;
            $slip['upah_lembur'] = $isFlat
                ? $slip['total_lembur_flat']
                : round($slip['jml_jam_lembur'] * $nilaiPerJam, 2);

            $projectKode  = strtolower($emp->project?->kode ?? '');
            $izinDipotong = !in_array($projectKode, ['khawista', 'purnama']);
            $slip['potongan_alpa'] = round($upahPenuh / 25 * ($slip['alpa'] + ($izinDipotong ? $slip['izin'] : 0)), 2);

            // Potongan khusus Khawista & NK
            $potonganInsentif = 0;
            if ($projectKode === 'khawista') {
                $subGroup = strtolower($member?->sub_group ?? '');
                if ($subGroup === 'construction') {
                    $potonganInsentif = round($slip['insentif'] / 25 * ($slip['izin'] + $slip['sakit'] + $slip['cuti']), 2);
                } elseif ($subGroup === 'piling') {
                    $potonganInsentif = round($slip['tunj_lapangan'] / 25 * $slip['izin'], 2);
                }
            } elseif ($projectKode === 'nk') {
                $stbVal = $slip['stb'] ?? 0;
                $potonganInsentif = round($slip['insentif'] / 25 * ($slip['izin'] + $slip['sakit'] + $slip['cuti'] + $stbVal), 2);
            } 
            $slip['potongan_insentif']  = $potonganInsentif;
            $slip['pot_tabung_oksigen'] = $data['pot_tabung_oksigen'] ?? ($payroll->pot_tabung_oksigen ?? 0);

            // TTT custom (per-project, dinamis) — harus masuk ke gaji_kotor juga.
            $customKeys = $emp->project_id ? $this->getTttItems($emp->project_id)->where('is_default', false)->pluck('key')->toArray() : [];
            $customSum  = 0;
            foreach ($customKeys as $key) {
                $slip[$key] = $mergedTttCustom[$key] ?? 0;
                $customSum += (float) $slip[$key];
            }
            $slip['custom_ttt_sum'] = $customSum;

            // Satu sumber perhitungan yang sama dengan Data Gaji & Slip Gaji
            $slip = $this->hitungGajiKotorBersih($slip, false, false, $bpjsPct['jht'], $bpjsPct['pensiun'], $bpjsPct['kes']);
        }

        // no_bpjs_tk/no_bpjs_kes dikirim bertahap (bukan selalu bareng semua field lain), jadi
        // kalau tidak ada di payload jangan ditimpa null — pertahankan nilai yang sudah tersimpan.
        $updateData = array_merge($slip, [
            'no_bpjs_tk'     => array_key_exists('no_bpjs_tk', $data)  ? $data['no_bpjs_tk']  : $payroll->no_bpjs_tk,
            'no_bpjs_kes'    => array_key_exists('no_bpjs_kes', $data) ? $data['no_bpjs_kes'] : $payroll->no_bpjs_kes,
            'catatan'        => $data['catatan']         ?? null,
            'dibuat_oleh'    => auth()->user()?->name   ?? 'System',
            'ttt_custom'     => $mergedTttCustom ?: null,
            'potongan_custom'=> $mergedPotonganCustom ?: null,
        ]);

        $allowedColumns = [
            'gaji_pokok','tunj_tetap','tunj_jabatan','kompensasi_pwt','upah_penuh',
            'ttt_perhari','dul','com_day','insentif','tunj_makan','tunj_produksi',
            'tunj_lapangan','tunj_kehadiran','tunj_pulsa','kompensasi_kontrak',
            'ttt_custom','potongan_custom','jml_jam_lembur','total_jam_ot_15x','total_jam_ot_2x',
            'upah_lembur','l_sabtu','l_libur','lembur_biasa','total_lembur_flat',
            'uang_hadir','h_kerja','h_sabtu','h_minggu_libur',
            'gaji_kotor','potongan_jht','potongan_pensiun','potongan_kes',
            'potongan_alpa','potongan_insentif','pot_tabung_oksigen','kekurangan_bulan_lalu','gaji_bersih',
            'izin','sakit','alpa','cuti','stb',
            'no_bpjs_tk','no_bpjs_kes',
            'dibuat_oleh','catatan',
        ];

        $originalAttrs = $payroll->getOriginal();

        foreach ($updateData as $k => $v) {
            if (in_array($k, $allowedColumns)) {
                $payroll->$k = $v;
            }
        }

        $hasChanges = false;
        foreach (array_diff($allowedColumns, ['dibuat_oleh']) as $col) {
            $old = $originalAttrs[$col] ?? null;
            $new = $payroll->$col;
            if (is_array($old) || is_array($new)) {
                $changed = json_encode($old) !== json_encode($new);
            } elseif (is_numeric($old) && is_numeric($new)) {
                $changed = (float) $old !== (float) $new;
            } else {
                $changed = (string) $old !== (string) $new;
            }
            if ($changed) {
                $hasChanges = true;
                break;
            }
        }

        $payroll->save();

        if ($hasChanges) {
            $empLog = Employee::find($employeeId);
            ActivityLog::record(
                'update',
                'Data Gaji',
                $empLog?->nama_lengkap ?? "ID:{$employeeId}",
                "Update manual data gaji {$tahun}/{$bulan}: {$empLog?->nama_lengkap} ({$empLog?->id_badge})"
            );
        }

        return response()->json(['ok' => true, 'message' => 'Berhasil disimpan.']);
    }

    public function updateEmployeeInfo(Request $request, Employee $employee)
    {
        if ($this->isViewer()) {
            return response()->json(['ok' => false, 'message' => 'Viewer tidak memiliki akses.'], 403);
        }

        $data = $request->validate([
            'tanggal_masuk'      => 'nullable|date',
            'ho_status_karyawan' => 'nullable|string|max:40',
            'ptkp'               => 'nullable|string|max:20',
            'no_rekening'        => 'nullable|string|max:50',
            'nama_bank'          => 'nullable|string|max:50',
        ]);

        $employeeData = array_intersect_key($data, array_flip(['tanggal_masuk', 'ptkp', 'no_rekening', 'nama_bank']));
        if ($employeeData) {
            $employee->update($employeeData);
        }
        if (array_key_exists('ho_status_karyawan', $data)) {
            EmployeeHoDetail::updateOrCreate(
                ['employee_id' => $employee->id],
                ['status_karyawan' => $data['ho_status_karyawan']]
            );
        }

        ActivityLog::record('update', 'Data Gaji', $employee->nama_lengkap, "Update data karyawan dari Data Gaji: {$employee->nama_lengkap}");

        return response()->json(['ok' => true]);
    }

    public function updateSalaryHistory(Request $request, int $employeeId)
    {
        if ($this->isViewer()) {
            return response()->json(['ok' => false, 'message' => 'Viewer tidak memiliki akses.'], 403);
        }

        $data = $request->validate([
            'label'   => 'required|string|max:150',
            'nominal' => 'nullable|numeric|min:0',
        ]);

        $urutan = EmployeeSalaryHistory::where('label', $data['label'])->value('urutan');

        EmployeeSalaryHistory::updateOrCreate(
            ['employee_id' => $employeeId, 'label' => $data['label']],
            ['nominal' => $data['nominal'] ?? 0, 'urutan' => $urutan]
        );

        return response()->json(['ok' => true]);
    }


    // ════════════════════════════════════════════════════════════
    // EXPORT SLIP EXCEL
    // ════════════════════════════════════════════════════════════
    public function exportSlipExcel(Request $request)
    {
        $tahun      = (int) $request->get('tahun', now()->year);
        $bulan      = (int) $request->get('bulan', now()->month);
        $employeeId = $request->get('employee_id');

        $employee = Employee::with(['position', 'project'])->findOrFail($employeeId);
        $isMd     = $employee->project?->tipe_gaji === 'md';
        $isHo     = $employee->project?->tipe_gaji === 'ho';

        // Ambil kelompok dari TimesheetMember
        $projectId = $this->activeProjectId();
        $member    = TimesheetMember::where('id_badge', $employee->id_badge)
            ->when($projectId, fn($q) => $q->where('project_id', $projectId))
            ->first();
        $isFlat    = $member?->kelompok === 'flat';

        // Satu sumber perhitungan yang sama dengan halaman Slip Gaji & Data Gaji —
        // persentase BPJS & daftar TTD diambil dari konfigurasi project (bukan lagi dari query string).
        $slip = $this->getSlipData((int) $employeeId, $tahun, $bulan)
            ?? $this->hitungSlipGaji($employee, $tahun, $bulan, $isFlat);
        $pctJht     = $slip['pct_jht']     ?? 2.0;
        $pctPensiun = $slip['pct_pensiun'] ?? 1.0;
        $pctKes     = $slip['pct_kes']     ?? 1.0;
        $ttdList    = $slip['ttd_list']    ?? [];

        $bulanNama  = $this->bulanNama();
        $periodeStr = $bulanNama[$bulan] . ' ' . $tahun;
        $potJht     = $slip['potongan_jht'];
        $potPensiun = $slip['potongan_pensiun'];
        $potKes     = $slip['potongan_kes'];
        $potAlpa    = $slip['potongan_alpa'] ?? 0;
        $potInsentif = $slip['potongan_insentif'] ?? 0;
        $potOksigen  = $slip['pot_tabung_oksigen'] ?? 0;
        $kekurangan  = $slip['kekurangan_bulan_lalu'] ?? 0;
        $totalPot   = $potJht + $potPensiun + $potKes + $potAlpa + $potInsentif + $potOksigen;
        $gajiPokok  = $slip['gaji_pokok'];
        $tunjTetap  = $slip['tunj_tetap'];
        $kompPwt    = $slip['kompensasi_pwt'];
        $upahLembur = $slip['upah_lembur'];
        $gajiKotor  = $slip['gaji_kotor'];
        $gajiBersih = $slip['gaji_bersih'];

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
            [$rowNoReg,   'No. Badge',     $employee->id_badge],
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

        $letters = ['A', 'B', 'C', 'D', 'E', 'F'];
        $li = 0;

        // A. Perolehan
        $addSectionHeader($letters[$li++], 'PEROLEHAN');
        $addItem('1.', 'Gaji Pokok / Upah', $gajiPokok);
        $addItem('2.', 'Tunjangan Tetap (Tunj. Jabatan)', $tunjTetap);
        $addItem('3.', 'Kompensasi PWT', $kompPwt);
        $perolehanSubtotal = $gajiPokok + $tunjTetap + $kompPwt;

        $tttSubtotal = 0;
        if ($isMd) {
            $addSectionHeader($letters[$li++], 'TUNJANGAN TIDAK TETAP (MD)');
            $addItem('1.', 'TTT per Hari (× ' . ($slip['h_kerja'] ?? 0) . ' hari)', $slip['ttt_total'] ?? 0, 'Rp ' . number_format($slip['ttt_perhari'] ?? 0, 0, ',', '.') . ' × ' . ($slip['h_kerja'] ?? 0));
            $addItem('2.', 'Uang Makan per Hari (× ' . ($slip['h_kerja'] ?? 0) . ' hari)', $slip['tunj_makan_total'] ?? 0, 'Rp ' . number_format($slip['tunj_makan'] ?? 0, 0, ',', '.') . ' × ' . ($slip['h_kerja'] ?? 0));
            $addItem('3.', 'Com Day (× ' . ($slip['h_kerja'] ?? 0) . ' hari)', $slip['com_day_total'] ?? 0, 'Rp ' . number_format($slip['com_day'] ?? 0, 0, ',', '.') . ' × ' . ($slip['h_kerja'] ?? 0));
            if ((float)($slip['insentif'] ?? 0) > 0) $addItem('4.', 'Insentif', $slip['insentif']);
            $tttSubtotal = ($slip['ttt_total'] ?? 0) + ($slip['tunj_makan_total'] ?? 0) + ($slip['com_day_total'] ?? 0) + ($slip['insentif'] ?? 0);
        } else {
            $tttItems = [];
            if ((float)($slip['tunj_makan']         ?? 0) > 0) $tttItems[] = ['Uang Makan',       $slip['tunj_makan']];
            if ((float)($slip['tunj_produksi']      ?? 0) > 0) $tttItems[] = ['Tunj. Produksi',   $slip['tunj_produksi']];
            if ((float)($slip['tunj_lapangan']      ?? 0) > 0) $tttItems[] = ['Tunj. Lapangan',   $slip['tunj_lapangan']];
            if ((float)($slip['tunj_kehadiran']     ?? 0) > 0) $tttItems[] = ['Tunj. Kehadiran',  $slip['tunj_kehadiran']];
            if ((float)($slip['tunj_pulsa']         ?? 0) > 0) $tttItems[] = ['Tunj. Pulsa',      $slip['tunj_pulsa']];
            if ((float)($slip['kompensasi_kontrak'] ?? 0) > 0) $tttItems[] = ['Komp. Kontrak',    $slip['kompensasi_kontrak']];
            if ((float)($slip['insentif']           ?? 0) > 0) $tttItems[] = ['Insentif',         $slip['insentif']];
            if ((float)($slip['com_day']            ?? 0) > 0) $tttItems[] = ['Com Day',          $slip['com_day']];

            if (count($tttItems) > 0) {
                $addSectionHeader($letters[$li++], 'TUNJANGAN TIDAK TETAP');
                foreach ($tttItems as $i => [$label, $val]) {
                    $addItem(($i + 1) . '.', $label, $val);
                }
                $tttSubtotal = array_sum(array_column($tttItems, 1));
            }
        }

        // C. Lembur (HO tidak punya lembur — timesheet cuma berlaku untuk project lapangan)
        $lemburSubtotal = 0;
        if (!$isHo) {
            $addSectionHeader($letters[$li++], 'LEMBUR');
            if ($isMd) {
                $addItem('1.', 'OT 1,5× — ' . ($slip['total_ot_15x'] ?? 0) . ' jam', null, ($slip['total_ot_15x'] ?? 0) . ' × 1,5 × Rp ' . number_format($slip['nilai_lembur_per_jam'] ?? 0, 0, ',', '.') . '/jam');
                $addItem('2.', 'OT 2×   — ' . ($slip['total_ot_2x']  ?? 0) . ' jam', null, ($slip['total_ot_2x']  ?? 0) . ' × 2 × Rp '   . number_format($slip['nilai_lembur_per_jam'] ?? 0, 0, ',', '.') . '/jam');
                $addItem('', 'Total Upah Lembur', $upahLembur);
                $lemburSubtotal = $upahLembur;
            } elseif ($isFlat) {
                $addItem('1.', 'Lembur Sabtu × ' . ($slip['l_sabtu'] ?? 0) . ' hari', ($slip['l_sabtu'] ?? 0) * ($slip['tarif_sabtu'] ?? 0), ($slip['l_sabtu'] ?? 0) . ' × Rp ' . number_format($slip['tarif_sabtu'] ?? 0, 0, ',', '.'));
                $addItem('2.', 'Lembur Libur × ' . ($slip['l_libur'] ?? 0) . ' hari', ($slip['l_libur'] ?? 0) * ($slip['tarif_libur'] ?? 0), ($slip['l_libur'] ?? 0) . ' × Rp ' . number_format($slip['tarif_libur'] ?? 0, 0, ',', '.'));
                if ((float)($slip['lembur_biasa'] ?? 0) > 0) $addItem('3.', 'Lembur Biasa × ' . ($slip['lembur_biasa'] ?? 0) . ' hari', ($slip['lembur_biasa'] ?? 0) * ($slip['tarif_biasa'] ?? 0), ($slip['lembur_biasa'] ?? 0) . ' × Rp ' . number_format($slip['tarif_biasa'] ?? 0, 0, ',', '.'));
                $addItem('', 'Total Lembur Flat', $slip['total_lembur_flat'] ?? 0);
                $lemburSubtotal = $slip['total_lembur_flat'] ?? 0;
            } else {
                $addItem('1.', 'Upah Lembur (' . ($slip['jml_jam_lembur'] ?? 0) . ' jam)', $upahLembur);
                $lemburSubtotal = $upahLembur;
            }
        }

        $addTotal('GAJI SEBULAN (KOTOR)', $gajiKotor, true);

        $rincianKotorParts = [$fmtRp($perolehanSubtotal) . ' (Perolehan)'];
        if ($tttSubtotal > 0)   $rincianKotorParts[] = $fmtRp($tttSubtotal) . ' (TTT)';
        if ($lemburSubtotal > 0) $rincianKotorParts[] = $fmtRp($lemburSubtotal) . ' (Lembur)';
        $ws->mergeCells("A{$r}:G{$r}");
        $ws->setCellValue("A{$r}", '= Rp ' . implode(' + Rp ', $rincianKotorParts));
        $ws->getStyle("A{$r}")->applyFromArray(['font' => ['italic' => true, 'size' => 7.5, 'name' => 'Arial', 'color' => ['argb' => 'FF888888']]]);
        $ws->getRowDimension($r)->setRowHeight(11);
        $r++;

        // D. Potongan
        $addSectionHeader($letters[$li++], 'POTONGAN WAJIB');
        $addItem('1.', "BPJS TK – JHT ({$pctJht}%)",        $potJht);
        $addItem('2.', "BPJS TK – Pensiun ({$pctPensiun}%)", $potPensiun);
        $addItem('3.', "BPJS Kesehatan ({$pctKes}%)",        $potKes);
        if ($potAlpa > 0) $addItem('4.', 'Potongan Alpa (' . ($slip['alpa'] ?? 0) . ' hari)', $potAlpa, '(Gapok+Tunj) / 25 × ' . ($slip['alpa'] ?? 0));
        if ($potInsentif > 0) $addItem('5.', 'Pot. Insentif (' . ($slip['sub_group'] ?? '') . ')', $potInsentif);
        if ($potOksigen > 0) $addItem('6.', 'Potongan Tabung Oksigen', $potOksigen);
        $addTotal('TOTAL POTONGAN', $totalPot);
        if ($isHo && (($slip['potongan_simulasi'] ?? 0) > 0 || ($slip['potongan_custom_sum'] ?? 0) > 0)) {
            $addItem('', 'Info: potensi potongan cuti/alpa & lainnya (belum dipotong)', ($slip['potongan_simulasi'] ?? 0) + ($slip['potongan_custom_sum'] ?? 0));
        }
        $r++;

        // ── Rincian Penghasilan Bersih — ringkasan akhir, sama seperti box di PDF/slip layar ──
        $rincianStart = $r;
        $ws->mergeCells("A{$r}:G{$r}");
        $ws->setCellValue("A{$r}", 'Rincian Penghasilan Bersih:');
        $ws->getStyle("A{$r}")->applyFromArray(['font' => ['italic' => true, 'size' => 8, 'name' => 'Arial', 'color' => ['argb' => 'FF666666']]]);
        $r++;

        $addRincianRow = function (string $label, string $displayVal) use ($ws, &$r) {
            $ws->mergeCells("A{$r}:F{$r}");
            $ws->setCellValue("A{$r}", $label);
            $ws->setCellValue("G{$r}", $displayVal);
            $ws->getStyle("A{$r}:G{$r}")->getFont()->setSize(8.5)->setName('Arial');
            $ws->getStyle("G{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $ws->getRowDimension($r)->setRowHeight(13);
            $r++;
        };
        $addRincianRow('Gaji Kotor', 'Rp ' . $fmtRp($gajiKotor));
        $addRincianRow('Total Potongan', '- Rp ' . $fmtRp($totalPot));
        if ($kekurangan != 0) {
            $addRincianRow('Kekurangan Bulan Lalu', ($kekurangan > 0 ? '+ ' : '') . 'Rp ' . $fmtRp($kekurangan));
        }

        // Netto — jadi baris penutup box Rincian (border+fill supaya kelihatan menyatu jadi 1 box)
        $ws->mergeCells("A{$r}:F{$r}");
        $ws->setCellValue("A{$r}", 'NETTO DITERIMA');
        $ws->setCellValue("G{$r}", 'Rp ' . $fmtRp($gajiBersih));
        $ws->getStyle("A{$r}:G{$r}")->applyFromArray(['font' => ['bold' => true, 'size' => 11, 'name' => 'Arial'], 'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM]]]);
        $ws->getStyle("G{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $ws->getRowDimension($r)->setRowHeight(18);
        $rincianEnd = $r;
        $r++;

        $ws->getStyle("A{$rincianStart}:G{$rincianEnd}")->applyFromArray([
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF7F7F7']],
            'borders' => ['outline' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFDDDDDD']]],
        ]);
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
            'gaji_kotor'          => round($gajiKotor, 2),
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
    // CORE: Hitung slip gaji HO (kantor pusat) — tidak ada timesheet,
    // gaji ditentukan langsung dari nilai yang tersimpan di EmployeePayroll.
    // ════════════════════════════════════════════════════════════
    private function hitungSlipGajiHo(Employee $emp, int $tahun, int $bulan): array
    {
        if (!$emp->relationLoaded('project')) {
            $emp->load('project');
        }
        if (!$emp->relationLoaded('hoDetail')) {
            $emp->load('hoDetail');
        }

        $saved = EmployeePayroll::where([
            'employee_id' => $emp->id,
            'tahun'       => $tahun,
            'bulan'       => $bulan,
        ])->first();

        $gajiPokok = (float) ($saved->gaji_pokok ?? 0);
        $tunjTetap = (float) ($saved->tunj_tetap ?? 0);

        return [
            'employee_id'           => $emp->id,
            'id_badge'              => $emp->id_badge,
            'nama_lengkap'          => $emp->nama_lengkap,
            'no_ktp'                => $emp->no_ktp,
            'jabatan'               => $emp->position?->nama_jabatan ?? '—',
            'kelompok'              => null,
            'tipe_project'          => 'ho',
            'project_kode'          => strtolower($emp->project?->kode ?? ''),
            'ptkp'                  => $emp->ptkp ?? '—',
            'tanggal_masuk'         => $emp->tanggal_masuk?->format('d-m-Y'),
            'ho_unit'               => $emp->hoDetail?->unit,
            'ho_status_karyawan'    => $emp->hoDetail?->status_karyawan,
            'tahun'                 => $tahun,
            'bulan'                 => $bulan,
            'gaji_pokok'            => $gajiPokok,
            'tunj_tetap'            => $tunjTetap,
            'tunj_jabatan'          => 0,
            'kompensasi_pwt'        => $saved->kompensasi_pwt ?? round(($gajiPokok + $tunjTetap) / 12, 2),
            'upah_penuh'            => $gajiPokok + $tunjTetap,
            'ttt_perhari'           => 0,
            'com_day'               => 0,
            'insentif'              => 0,
            'tunj_makan'            => 0,
            'tunj_produksi'         => 0,
            'tunj_lapangan'         => 0,
            'jml_jam_lembur'        => 0,
            'upah_lembur'           => 0,
            'l_sabtu'               => 0,
            'l_libur'               => 0,
            'lembur_biasa'          => 0,
            'total_lembur_flat'     => 0,
            'uang_hadir'            => 0,
            'h_kerja'               => 0,
            'gaji_kotor'            => 0,
            'potongan_jht'          => 0,
            'potongan_pensiun'      => 0,
            'potongan_kes'          => 0,
            'potongan_alpa'         => 0,
            'potongan_insentif'     => 0,
            'pot_tabung_oksigen'    => 0,
            'kekurangan_bulan_lalu' => (float) ($saved->kekurangan_bulan_lalu ?? 0),
            'gaji_bersih'           => 0,
            'izin'                  => (int) ($saved->izin ?? 0),
            'sakit'                 => (int) ($saved->sakit ?? 0),
            'alpa'                  => (int) ($saved->alpa ?? 0),
            'cuti'                  => (int) ($saved->cuti ?? 0),
            'stb'                   => 0,
            'hari_details'          => [],
        ];
    }


    // ════════════════════════════════════════════════════════════
    // SATU-SATUNYA SUMBER PERHITUNGAN: Gaji Kotor / Gaji Bersih / BPJS
    // Dipanggil setelah semua override manual (EmployeePayroll) diterapkan
    // ke $slip, supaya Data Gaji, Slip Gaji (layar/Excel/print), dan yang
    // tersimpan di DB selalu memakai rumus yang sama persis.
    // ════════════════════════════════════════════════════════════
    private function hitungGajiKotorBersih(array $slip, bool $isMd, bool $isHo = false, float $pctJht = 2.0, float $pctPensiun = 1.0, float $pctKes = 1.0): array
    {
        $gajiPokok = (float) ($slip['gaji_pokok'] ?? 0);
        $tunjTetap = (float) ($slip['tunj_tetap'] ?? 0);
        $upahPenuh = $gajiPokok + $tunjTetap;

        $potonganJht     = round($upahPenuh * $pctJht / 100);
        $potonganPensiun = round($upahPenuh * $pctPensiun / 100);
        $potonganKes     = round($upahPenuh * $pctKes / 100);

        if ($isHo) {
            // HO: tidak ada potongan alpa/izin sungguhan — hanya disimulasikan untuk info
            // (lihat blok $isHo di bawah), jadi gaji_bersih tidak dikurangi di sini.
            $slip['potongan_alpa'] = 0;

            $kompPwt = round($upahPenuh / 12, 2);
            $slip['kompensasi_pwt'] = $kompPwt;
            $gajiKotor = round($gajiPokok + $tunjTetap + $kompPwt + ($slip['custom_ttt_sum'] ?? 0), 2);
            $gajiBersih = round(
                $gajiKotor - $potonganJht - $potonganPensiun - $potonganKes
                + ($slip['kekurangan_bulan_lalu'] ?? 0),
                2
            );

            $alpaCount = (float) ($slip['alpa'] ?? 0);
            $cutiCount = (float) ($slip['cuti'] ?? 0);
            $slip['potongan_simulasi'] = round($upahPenuh / 25 * ($alpaCount + $cutiCount), 2);
            $slip['potongan_custom_sum'] = (float) ($slip['potongan_custom_sum'] ?? 0);

            $slip['potongan_jht']     = $potonganJht;
            $slip['potongan_pensiun'] = $potonganPensiun;
            $slip['potongan_kes']     = $potonganKes;
            $slip['gaji_kotor']       = $gajiKotor;
            $slip['gaji_bersih']      = $gajiBersih;

            return $slip;
        }

        // Potongan alpa dihitung ulang langsung dari kehadiran (izin/alpa) yang selalu live dari
        // timesheet — jangan pakai nilai tersimpan yang bisa basi kalau kehadiran berubah setelah disimpan.
        $alpaCount = (float) ($slip['alpa'] ?? 0);
        if ($isMd) {
            $potonganAlpa = $alpaCount > 0 ? round($upahPenuh / 25 * $alpaCount) : 0;
        } else {
            $projectKode  = strtolower($slip['project_kode'] ?? '');
            $izinDipotong = !in_array($projectKode, ['khawista', 'purnama'], true);
            $izinCount    = (float) ($slip['izin'] ?? 0);
            $potonganAlpa = round($upahPenuh / 25 * ($alpaCount + ($izinDipotong ? $izinCount : 0)), 2);
        }
        $slip['potongan_alpa'] = $potonganAlpa;

        if ($isMd) {
            $hBasic      = $slip['h_basic'] ?? 0;
            $hSabtu      = $slip['h_sabtu'] ?? 0;
            $uBasic      = round($upahPenuh / 17 * min($hBasic, 17), 2);
            $uKerja      = round((($slip['tunj_makan'] ?? 0) + ($slip['tunj_kehadiran'] ?? 0)) * ($slip['h_kerja'] ?? 0), 2);
            $comDayTotal = round(($slip['com_day'] ?? 0) * $hSabtu, 2);

            $slip['u_basic']           = $uBasic;
            $slip['u_kerja']           = $uKerja;
            $slip['com_day_total']     = $comDayTotal;
            $slip['ttt_total']         = round(($slip['ttt_perhari'] ?? 0) * ($slip['h_kerja'] ?? 0), 2);
            $slip['tunj_makan_total']  = round(($slip['tunj_makan'] ?? 0) * ($slip['h_kerja'] ?? 0), 2);

            $gajiKotor = round(
                $uBasic
                + ($slip['kompensasi_pwt'] ?? 0)
                + $uKerja
                + $comDayTotal
                + ($slip['upah_lembur'] ?? 0)
                + ($slip['tunj_pulsa'] ?? 0)
                + ($slip['kekurangan_bulan_lalu'] ?? 0),
                2
            );
            $gajiBersih = round(
                $gajiKotor - $potonganJht - $potonganPensiun - $potonganKes - ($slip['potongan_alpa'] ?? 0),
                2
            );
        } else {
            $isFlat  = ($slip['kelompok'] ?? '') === 'flat';
            $kompPwt = round($upahPenuh / 12, 2);
            $tttSum  = ($slip['tunj_makan'] ?? 0) + ($slip['tunj_produksi'] ?? 0) + ($slip['tunj_lapangan'] ?? 0)
                     + ($slip['tunj_kehadiran'] ?? 0) + ($slip['tunj_pulsa'] ?? 0) + ($slip['kompensasi_kontrak'] ?? 0)
                     + ($slip['insentif'] ?? 0) + ($slip['com_day'] ?? 0)
                     + ($slip['custom_ttt_sum'] ?? 0);

            $upahLembur = $isFlat ? (float) ($slip['total_lembur_flat'] ?? 0) : (float) ($slip['upah_lembur'] ?? 0);
            $uangHadir  = $isFlat ? (float) ($slip['uang_hadir'] ?? 0) : 0;

            $slip['kompensasi_pwt'] = $kompPwt;
            $slip['upah_lembur']    = $upahLembur;

            $gajiKotor = round($upahPenuh + $kompPwt + $tttSum + $upahLembur + $uangHadir, 2);

            $gajiBersih = round(
                $gajiKotor
                - $potonganJht - $potonganPensiun - $potonganKes
                - ($slip['potongan_alpa']      ?? 0)
                - ($slip['potongan_insentif']  ?? 0)
                - ($slip['pot_tabung_oksigen'] ?? 0)
                + ($slip['kekurangan_bulan_lalu'] ?? 0),
                2
            );
        }

        $slip['potongan_jht']     = $potonganJht;
        $slip['potongan_pensiun'] = $potonganPensiun;
        $slip['potongan_kes']     = $potonganKes;
        $slip['gaji_kotor']       = $gajiKotor;
        $slip['gaji_bersih']      = $gajiBersih;
        // Simpan persentase yang benar-benar dipakai — supaya tampilan (Excel/print) selalu
        // menampilkan angka yang sesuai dengan konfigurasi project & periode ini, bukan asumsi tetap 2/1/1.
        $slip['pct_jht']     = $pctJht;
        $slip['pct_pensiun'] = $pctPensiun;
        $slip['pct_kes']     = $pctKes;

        return $slip;
    }

    // ════════════════════════════════════════════════════════════
    // SLIP GAJI SATU KARYAWAN — dipakai bersama oleh slipGaji(), exportSlipExcel(),
    // dan SlipGajiExportController (print/export legacy)
    // ════════════════════════════════════════════════════════════
    public function getSlipData(int $employeeId, int $tahun, int $bulan): ?array
    {
        $employee = Employee::with(['position', 'project'])->find($employeeId);
        if (!$employee) {
            return null;
        }

        $isMd      = $employee->project?->tipe_gaji === 'md';
        $isHo      = $employee->project?->tipe_gaji === 'ho';
        // Pakai project milik karyawan itu sendiri (bukan project yang sedang aktif di sesi
        // viewer) — supaya kelompok/sub_group/TTT custom-nya selalu benar untuk karyawan ini,
        // apapun project yang sedang dipilih di UI.
        $projectId = $employee->project_id ?? $this->activeProjectId();
        $member    = TimesheetMember::where('id_badge', $employee->id_badge)
            ->when($projectId, fn($q) => $q->where('project_id', $projectId))
            ->first();
        $isFlat = $member?->kelompok === 'flat';

        if ($isHo) {
            $slip = $this->hitungSlipGajiHo($employee, $tahun, $bulan);
            $slip['sub_group'] = $employee->hoDetail?->unit;
        } else {
            if ($isMd) {
                $tsData = $this->getTimesheetData($employee->id, $tahun, $bulan);
                $slip   = $this->hitungSlipGajiMd($employee, $tahun, $bulan, $tsData);
            } else {
                $slip = $this->hitungSlipGaji($employee, $tahun, $bulan, $isFlat);
            }
            $slip['sub_group'] = $member?->sub_group;
        }

        $savedPayroll = EmployeePayroll::where([
            'employee_id' => $employee->id,
            'tahun'       => $tahun,
            'bulan'       => $bulan,
        ])->first();

        if ($savedPayroll) {
            $slip['tunj_makan']            = $savedPayroll->tunj_makan            ?? $slip['tunj_makan'];
            $slip['tunj_produksi']         = $savedPayroll->tunj_produksi         ?? $slip['tunj_produksi'];
            $slip['tunj_lapangan']         = $savedPayroll->tunj_lapangan         ?? $slip['tunj_lapangan'];
            $slip['tunj_kehadiran']        = $savedPayroll->tunj_kehadiran        ?? 0;
            $slip['tunj_pulsa']            = $savedPayroll->tunj_pulsa           ?? 0;
            $slip['kompensasi_kontrak']    = $savedPayroll->kompensasi_kontrak   ?? 0;
            $slip['insentif']              = $savedPayroll->insentif             ?? $slip['insentif'];
            $slip['com_day']               = $savedPayroll->com_day             ?? $slip['com_day'];
            $slip['gaji_pokok']            = $savedPayroll->gaji_pokok !== null ? $savedPayroll->gaji_pokok : $slip['gaji_pokok'];
            $slip['tunj_tetap']            = $savedPayroll->tunj_tetap !== null ? $savedPayroll->tunj_tetap : $slip['tunj_tetap'];
            $slip['tunj_jabatan']          = $savedPayroll->tunj_jabatan          ?? 0;
            $slip['kompensasi_pwt']        = $savedPayroll->kompensasi_pwt       ?? $slip['kompensasi_pwt'];
            $slip['upah_lembur']           = $savedPayroll->upah_lembur          ?? $slip['upah_lembur'];
            $slip['total_lembur_flat']     = $savedPayroll->total_lembur_flat    ?? $slip['total_lembur_flat'];
            $slip['jml_jam_lembur']        = $savedPayroll->jml_jam_lembur       ?? $slip['jml_jam_lembur'];
            $slip['l_sabtu']               = $savedPayroll->l_sabtu             ?? $slip['l_sabtu'];
            $slip['l_libur']               = $savedPayroll->l_libur             ?? $slip['l_libur'];
            $slip['lembur_biasa']          = $savedPayroll->lembur_biasa        ?? $slip['lembur_biasa'];
            $slip['uang_hadir']            = $savedPayroll->uang_hadir          ?? $slip['uang_hadir'];
            $slip['potongan_insentif']     = $savedPayroll->potongan_insentif   ?? 0;
            $slip['pot_tabung_oksigen']    = $savedPayroll->pot_tabung_oksigen  ?? 0;
            $slip['kekurangan_bulan_lalu'] = $savedPayroll->kekurangan_bulan_lalu ?? 0;
            $slip['stb']                   = $savedPayroll->stb                 ?? $slip['stb'] ?? 0;
            $slip['id']                    = $savedPayroll->id;

            if ($isMd) {
                $slip['ttt_perhari'] = $savedPayroll->ttt_perhari ?? $slip['ttt_perhari'];
                $slip['h_kerja']     = $savedPayroll->h_kerja     ?? $slip['h_kerja'];
                $slip['h_basic']     = $savedPayroll->h_basic     ?? $slip['h_basic'];
                $slip['h_sabtu']     = $savedPayroll->h_sabtu     ?? $slip['h_sabtu'];
            }

            // TTT custom (per-project, dinamis) — harus masuk ke gaji_kotor juga.
            $customKeys = $projectId ? $this->getTttItems($projectId)->where('is_default', false)->pluck('key')->toArray() : [];
            $customSum  = 0;
            foreach ($customKeys as $key) {
                $val = $savedPayroll->ttt_custom[$key] ?? 0;
                $slip[$key] = $val;
                $customSum += (float) $val;
            }
            $slip['custom_ttt_sum'] = $customSum;

            // Potongan dinamis (khusus HO) — info saja, tidak dikurangkan dari gaji_bersih.
            $potonganKeys = $projectId ? $this->getPotonganItems($projectId)->pluck('key')->toArray() : [];
            $potonganCustomSum = 0;
            foreach ($potonganKeys as $key) {
                $val = $savedPayroll->potongan_custom[$key] ?? 0;
                $slip['pot_custom_' . $key] = $val;
                $potonganCustomSum += (float) $val;
            }
            $slip['potongan_custom_sum'] = $potonganCustomSum;

            $bpjsPct = $projectId
                ? $this->getBpjsPct($projectId, $tahun, $bulan)
                : ['jht' => 2.0, 'pensiun' => 1.0, 'kes' => 1.0];
            $slip = $this->hitungGajiKotorBersih($slip, $isMd, $isHo, $bpjsPct['jht'], $bpjsPct['pensiun'], $bpjsPct['kes']);
        }

        $slip['no_rekening'] = $employee->no_rekening ?? null;
        $slip['ttd_list']    = $projectId
            ? $this->getTtdList($projectId)
            : [
                ['label' => 'Disetujui Oleh,', 'name' => 'H. Syahrul Akmal', 'jabatan' => 'Direktur Utama'],
                ['label' => 'Dibayar Oleh,',   'name' => 'Yulhamdani',       'jabatan' => 'Finance'],
            ];

        return $slip;
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

    // ── Konfigurasi BPJS % & TTD per project, berlaku per tanggal ──
    // Ambil versi konfigurasi yang berlaku untuk periode (tahun, bulan) tertentu — versi dengan
    // berlaku_mulai terbesar yang masih <= tanggal 1 periode itu. Kalau belum pernah dikonfigurasi,
    // pakai default lama (2%/1%/1%, TTD Direktur Utama & Finance) supaya tidak mengubah perilaku
    // yang sudah berjalan untuk project yang belum diatur.
    public function getBpjsPct(int $projectId, int $tahun, int $bulan): array
    {
        $periodeAwal = Carbon::create($tahun, $bulan, 1);
        $cfg = ProjectBpjsConfig::where('project_id', $projectId)
            ->where('berlaku_mulai', '<=', $periodeAwal)
            ->orderByDesc('berlaku_mulai')
            ->first();

        return [
            'jht'     => $cfg->pct_jht ?? 2.0,
            'pensiun' => $cfg->pct_pensiun ?? 1.0,
            'kes'     => $cfg->pct_kes ?? 1.0,
        ];
    }

    // TTD (tanda tangan) tidak perlu histori tanggal seperti BPJS — cuma 1 konfigurasi aktif
    // per project (siapa yang menandatangani slip SEKARANG).
    public function getTtdList(int $projectId): array
    {
        $cfg = ProjectTtdConfig::where('project_id', $projectId)->first();

        return $cfg->ttd_list ?? [
            ['label' => 'Disetujui Oleh,', 'name' => 'H. Syahrul Akmal', 'jabatan' => 'Direktur Utama'],
            ['label' => 'Dibayar Oleh,',   'name' => 'Yulhamdani',       'jabatan' => 'Finance'],
        ];
    }

    // Potongan dinamis (khusus HO) — mirip getTttItems() tapi tanpa auto-seed default,
    // karena tidak ada set potongan baku yang berlaku untuk semua project.
    private function getPotonganItems(int $projectId): \Illuminate\Support\Collection
    {
        return ProjectPotonganItem::where('project_id', $projectId)
            ->orderBy('urutan')
            ->get(['id', 'key', 'label', 'is_default', 'aktif']);
    }

    // Roster karyawan untuk Data Gaji / Slip Gaji. Project lapangan (giam/md/dst) memakai
    // TimesheetMember sebagai roster; project HO tidak punya timesheet sama sekali sehingga
    // rosternya diambil langsung dari tabel employees.
    private function getPayrollRoster(?int $projectId, ?int $tahun = null, ?int $bulan = null): \Illuminate\Support\Collection
    {
        $isHoProject = $projectId && Project::find($projectId)?->tipe_gaji === 'ho';

        if ($isHoProject) {
            return Employee::where('project_id', $projectId)
                ->where('status', 'AKTIF')
                ->with(['position', 'project', 'hoDetail'])
                ->orderBy('nama_lengkap')
                ->get()
                ->values()
                ->map(function ($emp, $idx) {
                    $m = new \stdClass();
                    $m->employee  = $emp;
                    $m->kelompok  = null;
                    $m->sub_group = $emp->hoDetail?->unit;
                    $m->urutan    = $idx + 1;
                    $m->id_badge  = null;
                    $m->tipe      = '7jam';
                    return $m;
                });
        }

        // Karyawan yang di-terminate tetap tampil sampai akhir bulan keluarnya (supaya gaji
        // bulan terakhirnya masih bisa diproses), tapi harus hilang otomatis di bulan-bulan
        // sesudahnya — bukan cuma sekali dicek waktu terminate (TimesheetMember.aktif dulu
        // hanya di-set false satu kali di titik itu, jadi nyangkut terus kalau bulan keluarnya
        // sama dengan bulan saat di-terminate).
        $periodeAwal = ($tahun && $bulan) ? Carbon::create($tahun, $bulan, 1) : null;

        return TimesheetMember::where('aktif', true)
            ->when($projectId, fn($q, $pid) => $q->where('project_id', $pid))
            ->when($periodeAwal, fn($q) => $q->whereHas('employee', function ($eq) use ($periodeAwal) {
                $eq->whereNull('tanggal_keluar')->orWhere('tanggal_keluar', '>=', $periodeAwal);
            }))
            ->with('employee.position', 'employee.project')
            ->orderBy('urutan')->orderBy('id_badge')
            ->get();
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