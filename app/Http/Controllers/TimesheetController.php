<?php
// TimesheetController.php
namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Timesheet;
use App\Models\TimesheetMember;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class TimesheetController extends Controller
{
    /**
     * Nama bulan (1-12) dalam Bahasa Indonesia — dipakai di index() dan export().
     */
    private function namaBulanList(): array
    {
        return [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];
    }

    /**
     * Nama override hardcode per badge (khusus Giam, bisa dipindah ke DB nanti).
     * Dipakai di index() dan export(), digabung dengan override dari DB (nama_override member).
     */
    private function namaOverrideHardcode(): array
    {
        return [
            'AKM-EW-0236' => 'SASTRO',
            'AKM-EW-0197' => 'M YASIR SIREGAR',
            'AKM-EW-0073' => 'EVANDRI WANDA',
            'AKM-EW-0013' => 'M RAPIQI',
            'AKM-EW-0068' => 'YUDHA PRATAMA',
            'AKM-EW-0102' => 'JAMI MARUZUKI',
            'AKM-EW-0117' => 'M REVAL AL AKHYAR',
            'AKM-EW-0127' => 'MUHAMMAD RENO',
            'AKM-EW-0167' => 'M HUSNI IQBAL',
        ];
    }

    public function index(Request $request)
    {
        $tahun = (int) $request->get('tahun', now()->year);
        $bulan = (int) $request->get('bulan', now()->month);
        $search = $request->get('search', '');

        $daysInMonth = Carbon::create($tahun, $bulan)->daysInMonth;
        $dayNamesID = ['Mgg', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

        $holidays = \App\Models\Holiday::inMonth($tahun, $bulan)
            ->get()->keyBy(fn($h) => (int) $h->tanggal->format('j'));

        $days = $this->buildDaysArray($tahun, $bulan, $daysInMonth, $dayNamesID, $holidays);

        // Ambil project aktif user yang login
        $projectId = $this->activeProjectId();

        if (!$projectId && auth()->user()->hasRole('super-admin')) {
            $firstProject = \App\Models\Project::where('is_active', true)
                ->orderBy('id')->first();
            if ($firstProject) {
                $projectId = $firstProject->id;
                // Simpan ke session supaya konsisten
                session(['active_project_kode' => $firstProject->id]);
            }
        }

        // Ambil members sesuai project — karyawan yang di-terminate tetap tampil sampai akhir
        // bulan keluarnya, lalu otomatis hilang di bulan-bulan sesudahnya (dihitung dari bulan
        // yang sedang dibuka, bukan sekali cek waktu terminate — lihat getPayrollRoster()).
        $periodeAwal = Carbon::create($tahun, $bulan, 1);
        $members = TimesheetMember::where('aktif', true)
            ->when($projectId, fn($q, $pid) => $q->where('project_id', $pid))
            ->whereHas('employee', fn($eq) => $eq->whereNull('tanggal_keluar')->orWhere('tanggal_keluar', '>=', $periodeAwal))
            ->orderBy('urutan')->orderBy('id_badge')->get();

        $badgeList = $members->pluck('id_badge')->toArray();
        $namaOverrideDb = $members->whereNotNull('nama_override')
            ->pluck('nama_override', 'id_badge')->toArray();

        // Tipe timesheet per badge (8jam atau 7jam)
        $tipePerBadge = $members->pluck('tipe', 'id_badge')->toArray();

        // Nama override hardcode (khusus Giam, bisa dipindah ke DB nanti)
        $namaOverride = array_merge($this->namaOverrideHardcode(), $namaOverrideDb);

        // Query karyawan aktif dari member list
        $employees = Employee::whereIn('id_badge', $badgeList)
            ->when($projectId, fn($q) => $q->where('project_id', $projectId))
            ->with('position')
            ->when($search, fn($q) => $q->where(
                fn($q2) =>
                $q2->where('nama_lengkap', 'like', "%$search%")
                    ->orWhere('id_badge', 'like', "%$search%")
            ))
            ->orderBy('nama_lengkap')
            ->get();

        // Tambah karyawan terminated yang masih punya timesheet bulan ini
        $terminatedWithTs = Employee::where('status', 'NONAKTIF')
            ->whereNotIn('id', $employees->pluck('id'))
            ->when($projectId, fn($q) => $q->where('project_id', $projectId))
            ->with('position')
            ->whereHas(
                'timesheets',
                fn($q) =>
                $q->where('tahun', $tahun)->where('bulan', $bulan)
            )
            ->when($search, fn($q) => $q->where(
                fn($q2) =>
                $q2->where('nama_lengkap', 'like', "%$search%")
                    ->orWhere('id_badge', 'like', "%$search%")
            ))
            ->get();

        $employees = $employees->concat($terminatedWithTs)->sortBy('nama_lengkap')->values();

        $timesheets = Timesheet::where('tahun', $tahun)
            ->where('bulan', $bulan)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->get()->groupBy('employee_id');

        // Peta id_badge => member (first occurrence, sama seperti firstWhere) supaya
        // lookup sub_group per baris di bawah tidak perlu scan ulang $members tiap kali.
        $membersByBadge = $members->unique('id_badge')->keyBy('id_badge');

        $grid = $employees->map(fn($emp) => $this->buildEmployeeGridRow(
            $emp,
            $timesheets,
            $daysInMonth,
            $namaOverride,
            $tipePerBadge,
            $tahun,
            $bulan,
            $membersByBadge
        ));

        $bulanList = $this->namaBulanList();

        // Ambil project info untuk frontend
        $projectInfo = null;
        if ($projectId) {
            $projectInfo = \App\Models\Project::find($projectId, ['id', 'kode', 'nama', 'tipe_timesheet', 'tipe_gaji']);
        }

        return Inertia::render('Timesheet/Index', [
            'grid' => $grid,
            'days' => $days,
            'tahun' => $tahun,
            'bulan' => $bulan,
            'search' => $search,
            'bulan_list' => $bulanList,
            'days_in_month' => $daysInMonth,
            'project_info' => $projectInfo,
            'members' => TimesheetMember::with('employee.position')
                ->when($projectId, fn($q, $pid) => $q->where('project_id', $pid))
                ->orderBy('urutan')->orderBy('id_badge')->get()
                ->map(fn($m) => [
                    'id' => $m->id,
                    'id_badge' => $m->id_badge,
                    'nama_override' => $m->nama_override,
                    'urutan' => $m->urutan,
                    'aktif' => $m->aktif,
                    'tipe' => $m->tipe,
                    'kelompok' => $m->kelompok,
                    'sub_group' => $m->sub_group,
                    'nama_lengkap' => $m->employee?->nama_lengkap ?? '—',
                    'jabatan' => $m->employee?->position?->nama_jabatan ?? '—',
                ]),
            'all_holidays' => \App\Models\Holiday::orderBy('tanggal')->get()->map(fn($h) => [
                'id' => $h->id,
                'tanggal' => $h->tanggal->format('Y-m-d'),
                'tanggal_fmt' => $h->tanggal->format('d M Y'),
                'keterangan' => $h->keterangan,
                'tipe' => $h->tipe,
            ]),
            'available_employees' => Employee::aktif()
                ->when($projectId, fn($q, $pid) => $q->where('project_id', $pid))
                ->with('position')
                ->orderBy('nama_lengkap')
                ->get(['id', 'id_badge', 'nama_lengkap', 'position_id'])
                ->map(fn($e) => [
                    'id_badge' => $e->id_badge,
                    'nama_lengkap' => $e->nama_lengkap,
                    'jabatan' => $e->position?->nama_jabatan ?? '-',
                ]),
        ]);
    }

    /**
     * Bangun array info tiap hari dalam sebulan (dipakai untuk header kalender di frontend).
     * Ekstraksi murni dari index() — tidak mengubah urutan/hasil, hanya isolasi.
     */
    private function buildDaysArray(int $tahun, int $bulan, int $daysInMonth, array $dayNamesID, $holidays): array
    {
        $days = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = Carbon::create($tahun, $bulan, $d);
            $holiday = $holidays->get($d);
            $days[] = [
                'day' => $d,
                'date' => $date->format('Y-m-d'),
                'day_name' => $dayNamesID[$date->dayOfWeek],
                'is_sunday' => $date->isSunday(),
                'is_saturday' => $date->isSaturday(),
                'is_holiday' => $holiday !== null,
                'holiday_label' => $holiday?->keterangan,
                'holiday_tipe' => $holiday?->tipe,
            ];
        }
        return $days;
    }

    /**
     * Hitung satu baris grid timesheet untuk seorang karyawan (rekap hadir/izin/sakit/alpa/cuti
     * + OT jam untuk tipe 8jam). Ekstraksi murni dari closure ->map() di index() — logika dan
     * urutan perhitungan tidak diubah sama sekali.
     */
    private function buildEmployeeGridRow(
        $emp,
        $timesheets,
        int $daysInMonth,
        array $namaOverride,
        array $tipePerBadge,
        int $tahun,
        int $bulan,
        $membersByBadge
    ): array {
        $empTs = $timesheets->get($emp->id, collect());
        $tsMap = $empTs->keyBy('hari');
        $tipe = $tipePerBadge[$emp->id_badge] ?? '7jam';
        $regJam = $tipe === '8jam' ? 8 : 7; // jam reguler per hari

        $rows = [];
        $totalHadir = 0;
        $totalIzin = 0;
        $totalSakit = 0;
        $totalAlpa = 0;
        $totalCuti = 0;
        $totalOtJam = 0; // total jam lembur (khusus 8jam)

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $val = $tsMap->get($d)?->nilai;
            $rows[$d] = $val;
            if (!$val)
                continue;
            $up = strtoupper($val);

            if ($up === 'I')
                $totalIzin++;
            elseif ($up === 'S')
                $totalSakit++;
            elseif ($up === 'A')
                $totalAlpa++;
            elseif ($up === 'C')
                $totalCuti++;
            elseif (is_numeric($val)) {
                $jam = (float) $val;
                $totalHadir++;

                // Hitung OT untuk MD (8jam)
                if ($tipe === '8jam') {
                    $date = Carbon::create($tahun, $bulan, $d);
                    $isSat = $date->isSaturday();
                    $isSun = $date->isSunday();

                    if ($isSun) {
                        // Minggu: semua jam = OT 2x
                        $totalOtJam += $jam;
                    } elseif ($isSat) {
                        // Sabtu: semua jam = OT 1.5x dari jam pertama
                        $totalOtJam += $jam;
                    } else {
                        // Hari biasa: lebih dari 8 jam = OT
                        if ($jam > $regJam) {
                            $totalOtJam += ($jam - $regJam);
                        }
                    }
                }
            }
        }

        $result = [
            'id' => $emp->id,
            'id_badge' => $emp->id_badge,
            'nama_lengkap' => $namaOverride[$emp->id_badge] ?? $emp->nama_lengkap,
            'jabatan' => $emp->position?->nama_jabatan ?? '-',
            'tipe' => $tipe,
            'sub_group' => $membersByBadge->get($emp->id_badge)?->sub_group,
            'days' => $rows,
            'total_hadir' => $totalHadir,
            'total_izin' => $totalIzin,
            'total_sakit' => $totalSakit,
            'total_alpa' => $totalAlpa,
            'total_cuti' => $totalCuti,
        ];

        if ($tipe === '8jam') {
            $result['total_ot_jam'] = round($totalOtJam, 2);
        }

        return $result;
    }

    public function update(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'tahun' => 'required|integer',
            'bulan' => 'required|integer|between:1,12',
            'hari' => 'required|integer|between:1,31',
            'nilai' => 'nullable|string|max:10',
        ]);

        $nilai = $request->nilai;
        if ($nilai !== null && $nilai !== '') {
            if (is_numeric($nilai)) {
                $nilai = (string) $nilai;
            } else {
                $nilai = strtoupper(trim($nilai));
                if (!in_array($nilai, ['I', 'S', 'A', 'C', 'STB']))
                    $nilai = null;
            }
        } else {
            $nilai = null;
        }

        if ($nilai === null) {
            Timesheet::where([
                'employee_id' => $request->employee_id,
                'tahun' => $request->tahun,
                'bulan' => $request->bulan,
                'hari' => $request->hari,
            ])->delete();
        } else {
            Timesheet::updateOrCreate(
                [
                    'employee_id' => $request->employee_id,
                    'tahun' => $request->tahun,
                    'bulan' => $request->bulan,
                    'hari' => $request->hari
                ],
                ['nilai' => $nilai]
            );
        }

        // Log hanya untuk nilai non-numerik (I/S/A/C) supaya tidak spam
        // Input jam kerja (angka) dicatat sekali per hari saja
        if ($nilai !== null) {
            $emp = Employee::find($request->employee_id);
            \App\Models\ActivityLog::record(
                'update',
                'Timesheet',
                $emp?->nama_lengkap ?? "ID:{$request->employee_id}",
                "Input timesheet {$request->tahun}/{$request->bulan} hari {$request->hari}: {$nilai}"
            );
        }

        $this->recalcPayroll(
            $request->employee_id,
            $request->tahun,
            $request->bulan
        );

        return response()->json(['ok' => true, 'nilai' => $nilai]);
    }

    private function recalcPayroll(int $employeeId, int $tahun, int $bulan): void
    {
        try {
            $emp = Employee::with(['position', 'project'])->find($employeeId);
            if (!$emp) return;

            $projectId = $emp->project_id;
            $member = TimesheetMember::where('id_badge', $emp->id_badge)
                ->where('project_id', $projectId)
                ->first();
            if (!$member) return;

            $isMd   = $emp->project?->tipe_gaji === 'md';
            $isFlat = $member->kelompok === 'flat';

            $payrollController = new PayrollController();

            $existing = \App\Models\EmployeePayroll::where([
                'employee_id' => $employeeId,
                'tahun'       => $tahun,
                'bulan'       => $bulan,
            ])->first();

            if ($isMd) {
                $tsData = $payrollController->getTimesheetDataPublic($employeeId, $tahun, $bulan);
                $mdOverride = [];
                if ($existing) {
                    if ($existing->gaji_pokok)            $mdOverride['gaji_pokok']            = $existing->gaji_pokok;
                    if ($existing->tunj_tetap)            $mdOverride['tunj_tetap']            = $existing->tunj_tetap;
                    if ($existing->tunj_makan)            $mdOverride['tunj_makan']            = $existing->tunj_makan;
                    if ($existing->tunj_kehadiran)        $mdOverride['tunj_kehadiran']        = $existing->tunj_kehadiran;
                    if ($existing->com_day)               $mdOverride['com_day']               = $existing->com_day;
                    if ($existing->tunj_pulsa)            $mdOverride['tunj_pulsa']            = $existing->tunj_pulsa;
                    if ($existing->kompensasi_pwt)        $mdOverride['kompensasi_pwt']        = $existing->kompensasi_pwt;
                    if ($existing->kekurangan_bulan_lalu) $mdOverride['kekurangan_bulan_lalu'] = $existing->kekurangan_bulan_lalu;
                }
                $slip = $payrollController->hitungSlipGajiMdPublic($emp, $tahun, $bulan, $tsData, $mdOverride);
            } else {
                $slip = $payrollController->hitungSlipGajiPublic($emp, $tahun, $bulan, $isFlat);
            }

            if ($existing) {
                $payroll = $existing;
            } else {
                $payroll = new \App\Models\EmployeePayroll();
                $payroll->employee_id  = $employeeId;
                $payroll->tahun        = $tahun;
                $payroll->bulan        = $bulan;
                $payroll->gaji_pokok   = $slip['gaji_pokok'];
                $payroll->tunj_tetap   = $slip['tunj_tetap'];
                $payroll->tunj_jabatan = 0;
                $payroll->kompensasi_pwt = $slip['kompensasi_pwt'];
                $payroll->ttt_perhari  = 0;
                $payroll->com_day      = 0;
                $payroll->insentif     = 0;
                $payroll->tunj_makan   = 0;
                $payroll->tunj_produksi = 0;
                $payroll->tunj_lapangan = 0;
                $payroll->tunj_kehadiran = 0;
                $payroll->tunj_pulsa   = 0;
                $payroll->kompensasi_kontrak = 0;
                $payroll->uang_hadir   = 0;
                $payroll->kekurangan_bulan_lalu = 0;
                $payroll->lembur_biasa = 0;
                $payroll->ptkp         = $emp->ptkp;
                $payroll->dibuat_oleh  = 'System (auto)';
            }

            // Field kalkulasi — selalu update dari timesheet
            $payroll->jml_jam_lembur    = $slip['jml_jam_lembur'];
            $payroll->upah_lembur       = $slip['upah_lembur'];
            if ($isMd) {
                $payroll->h_basic = $slip['h_basic'] ?? 0;
                $payroll->u_basic = $slip['u_basic'] ?? 0;
                $payroll->u_kerja = $slip['u_kerja'] ?? 0;
            }
            $payroll->l_sabtu           = $slip['l_sabtu'];
            $payroll->l_libur           = $slip['l_libur'];
            $payroll->total_lembur_flat = $slip['total_lembur_flat'];
            $payroll->h_kerja           = $slip['h_kerja'];
            $payroll->izin              = $slip['izin'];
            $payroll->sakit             = $slip['sakit'];
            $payroll->alpa              = $slip['alpa'];
            $payroll->cuti              = $slip['cuti'];
            $payroll->stb               = $slip['stb'] ?? 0;

            // Recalc gaji kotor & bersih dari nilai yang sudah tersimpan
            $gp  = $payroll->gaji_pokok   ?? $slip['gaji_pokok'];
            $tt  = $payroll->tunj_tetap   ?? $slip['tunj_tetap'];
            $tj  = $payroll->tunj_jabatan ?? 0;
            $pwt = round(($gp + $tt + $tj) / 12, 2);

            $payroll->kompensasi_pwt = $pwt;
            $payroll->upah_penuh     = $gp + $tt + $tj;

            $tttSum = ($payroll->com_day             ?? 0)
                    + ($payroll->insentif            ?? 0)
                    + ($payroll->tunj_makan          ?? 0)
                    + ($payroll->tunj_produksi       ?? 0)
                    + ($payroll->tunj_lapangan       ?? 0)
                    + ($payroll->tunj_kehadiran      ?? 0)
                    + ($payroll->tunj_pulsa          ?? 0)
                    + ($payroll->kompensasi_kontrak  ?? 0);

            $customSum = $payroll->ttt_custom ? array_sum($payroll->ttt_custom) : 0;
            $uangHadir  = $payroll->uang_hadir          ?? 0;
            $kekurangan = $payroll->kekurangan_bulan_lalu ?? 0;

            $upahPenuh = $gp + $tt + $tj;

            $projectKode  = strtolower($emp->project?->kode ?? '');
            $izinDipotong = !in_array($projectKode, ['khawista', 'purnama']);
            $potonganAlpa = round($upahPenuh / 25 * ($slip['alpa'] + ($izinDipotong ? $slip['izin'] : 0)), 2);

            if ($isMd) {
                $gajiKotor = $slip['gaji_kotor'];
            } elseif ($isFlat) {
                $gajiKotor = $gp + $tt + $tj + $pwt + $tttSum + $customSum + $slip['total_lembur_flat'] + $uangHadir;
            } else {
                $gajiKotor = $gp + $tt + $tj + $pwt + $tttSum + $customSum + $slip['upah_lembur'];
            }

            $jht     = $payroll->_no_jht     ? 0 : round($upahPenuh * 0.02);
            $pensiun = $payroll->_no_pensiun ? 0 : round($upahPenuh * 0.01);
            $kes     = $payroll->_no_kes     ? 0 : round($upahPenuh * 0.01);
            $alpa    = $payroll->_no_alpa    ? 0 : $potonganAlpa;

            $payroll->potongan_alpa    = $alpa;
            $payroll->potongan_jht     = $jht;
            $payroll->potongan_pensiun = $pensiun;
            $payroll->potongan_kes     = $kes;
            $payroll->gaji_kotor       = round($gajiKotor, 2);
            $potonganInsentif = 0;
            if ($projectKode === 'nk') {
                $insentifVal = $payroll->insentif ?? 0;
                $stbVal = $slip['stb'] ?? 0;
                $potonganInsentif = round($insentifVal / 25 * ($slip['izin'] + $slip['sakit'] + $slip['cuti'] + $stbVal), 2);
            }
            $payroll->potongan_insentif = $potonganInsentif;
            $payroll->stb               = $slip['stb'] ?? 0;
            if ($isMd) {
                $payroll->gaji_bersih = round($gajiKotor - $jht - $pensiun - $kes - $alpa, 2);
            } else {
                $payroll->gaji_bersih = round($gajiKotor - $jht - $pensiun - $kes - $alpa + $kekurangan, 2);
            }

            $payroll->save();

        } catch (\Throwable $e) {
            \Log::error('Auto recalc payroll failed: ' . $e->getMessage());
        }
    }

    public function export(Request $request)
    {
        $tahun = (int) $request->get('tahun', now()->year);
        $bulan = (int) $request->get('bulan', now()->month);
        $ttdHr = $request->get('ttd_hr', '');
        $ttdPm = $request->get('ttd_pm', '');

        $projectId = $this->activeProjectId();

        // Opsi A: default ke project pertama kalau super admin belum pilih project
        if (!$projectId && auth()->user()->hasRole('super-admin')) {
            $firstProject = \App\Models\Project::where('is_active', true)
                ->orderBy('id')->first();
            if ($firstProject) {
                $projectId = $firstProject->id;
                session(['active_project_kode' => $firstProject->id]);
            }
        }

        $bulanNama = $this->namaBulanList();
        $dayNames = ['Mgg', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

        $daysInMonth = Carbon::create($tahun, $bulan)->daysInMonth;
        $bulanStr = $bulanNama[$bulan];

        // Nama override
        $namaOverride = $this->namaOverrideHardcode();

        $periodeAwal = Carbon::create($tahun, $bulan, 1);
        $members = TimesheetMember::where('aktif', true)
            ->when($projectId, fn($q, $pid) => $q->where('project_id', $pid))
            ->whereHas('employee', fn($eq) => $eq->whereNull('tanggal_keluar')->orWhere('tanggal_keluar', '>=', $periodeAwal))
            ->orderBy('urutan')->orderBy('id_badge')->get();
        $badgeList = $members->pluck('id_badge')->toArray();
        $namaOverrideDb = $members->whereNotNull('nama_override')
            ->pluck('nama_override', 'id_badge')->toArray();
        $namaOverride = array_merge($namaOverride, $namaOverrideDb);

        $employees = Employee::whereIn('id_badge', $badgeList)
            ->when($projectId, fn($q) => $q->where('project_id', $projectId))
            ->with('position')->orderBy('nama_lengkap')->get();

        $timesheets = Timesheet::where('tahun', $tahun)->where('bulan', $bulan)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->get()->groupBy('employee_id');

        // Ambil hari libur bulan ini
        $holidays = \App\Models\Holiday::inMonth($tahun, $bulan)->get()
            ->keyBy(fn($h) => (int) $h->tanggal->format('j'));

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setTitle('Resume');
        $sheet->setShowGridlines(false);

        // ── Struktur Kolom ──
        // A=No | B=Nama | C=Badge(hidden) | D=Jabatan | E..=hari | ..=summary
        $dayStartCol = 5; // kolom E
        $summaryStart = $dayStartCol + $daysInMonth;
        $lastCol = $this->colLetter($summaryStart + 4);

        // ── HEADER JUDUL ──
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A1', 'REKAP TIMESHEET');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->setCellValue('A2', "GAJI {$bulanStr} ( PERIODE 01 - {$daysInMonth} {$bulanStr} {$tahun} )");
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->mergeCells("A3:{$lastCol}3");
        $sheet->setCellValue('A3', 'Karyawan Construction Project PT. ANDALAS KARYA MULIA ( CENTRAL )');
        $sheet->getStyle('A3')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->getRowDimension(4)->setRowHeight(6);
        $sheet->getRowDimension(5)->setRowHeight(6);

        // ── HEADER KOLOM (row 6) ──
        $hdrStyle = [
            'font' => ['bold' => true, 'size' => 8, 'color' => ['rgb' => '000000']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F4B942']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '999999']]],
        ];

        foreach ([
            'A' => ['No.', 3.5],
            'B' => ['Nama Karyawan', 26],
            'C' => ['Badge', 13],
            'D' => ['Jabatan', 18],
        ] as $col => [$label, $width]) {
            $sheet->setCellValue($col . '6', $label);
            $sheet->getStyle($col . '6')->applyFromArray($hdrStyle);
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        // ── SEMBUNYIKAN kolom C (Badge) ──
        $sheet->getColumnDimension('C')->setVisible(false);

        // Header hari
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = Carbon::create($tahun, $bulan, $d);
            $col = $this->colLetter($dayStartCol + $d - 1);
            $isSun = $date->isSunday();
            $isSat = $date->isSaturday();
            $holiday = $holidays->get($d);
            $isHoliday = $holiday !== null && !$isSun;

            $dayLabel = $dayNames[$date->dayOfWeek] . ', ' . sprintf('%02d', $d) . '-'
                . sprintf('%02d', $bulan) . '-' . substr($tahun, 2);

            // Warna header: Minggu=merah, Sabtu=hijau, Libur=oranye, Normal=putih
            [$bgColor, $txtColor] = $this->dayHeaderColors($isSun, $isHoliday, $isSat);

            $sheet->setCellValue($col . '6', $dayLabel);
            $sheet->getStyle($col . '6')->applyFromArray([
                'font' => ['bold' => true, 'size' => 7, 'color' => ['rgb' => $txtColor]],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'textRotation' => 90, 'wrapText' => true],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '999999']]],
            ]);
            $sheet->getColumnDimension($col)->setWidth(3.2);
        }

        // Header summary
        $summaryDefs = [
            ['Total Hari Kerja', 10, '4CAF50', 'FFFFFF'],
            ['Izin', 7, '2196F3', 'FFFFFF'],
            ['Sakit', 7, 'FF9800', 'FFFFFF'],
            ['Alpha', 7, 'F44336', 'FFFFFF'],
            ['Cuti', 7, '9C27B0', 'FFFFFF'],
        ];
        foreach ($summaryDefs as $i => [$label, $width, $bg, $txt]) {
            $col = $this->colLetter($summaryStart + $i);
            $sheet->setCellValue($col . '6', $label);
            $sheet->getStyle($col . '6')->applyFromArray([
                'font' => ['bold' => true, 'size' => 8, 'color' => ['rgb' => $txt]],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '999999']]],
            ]);
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        $sheet->getRowDimension(6)->setRowHeight(80);

        // Row 7: nomor kolom
        $sheet->setCellValue('A7', '1');
        $sheet->setCellValue('B7', '2');
        $sheet->setCellValue('C7', '3');
        $sheet->setCellValue('D7', '4');
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $sheet->setCellValue($this->colLetter($dayStartCol + $d - 1) . '7', $d + 4);
        }
        foreach (range(0, 4) as $i) {
            $sheet->setCellValue($this->colLetter($summaryStart + $i) . '7', $summaryStart + $i);
        }
        $sheet->getStyle("A7:{$lastCol}7")->applyFromArray([
            'font' => ['size' => 7],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F0F0F0']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
        ]);
        $sheet->getRowDimension(7)->setRowHeight(10);

        // ── DATA ROWS ──
        $dataRow = 8;
        foreach ($employees as $idx => $emp) {
            $empTs = $timesheets->get($emp->id, collect())->keyBy('hari');
            $nama = strtoupper($namaOverride[$emp->id_badge] ?? $emp->nama_lengkap);
            $jabatan = strtoupper($emp->position?->nama_jabatan ?? '-');
            $rowBg = ($idx % 2 === 0) ? 'FFFFFF' : 'F8F8F8';

            // Fixed cols
            $sheet->setCellValue('A' . $dataRow, $idx + 1);
            $sheet->setCellValue('B' . $dataRow, $nama);
            $sheet->setCellValue('C' . $dataRow, $emp->id_badge);  // tersembunyi
            $sheet->setCellValue('D' . $dataRow, $jabatan);

            $sheet->getStyle("A{$dataRow}:D{$dataRow}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rowBg]],
                'font' => ['size' => 8],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
            ]);
            $sheet->getStyle('A' . $dataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Day cells — pakai nilai dari DB tapi set conditional formatting
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $col = $this->colLetter($dayStartCol + $d - 1);
                $cellRef = $col . $dataRow;
                $date = Carbon::create($tahun, $bulan, $d);
                $isSun = $date->isSunday();
                $holiday = $holidays->get($d);
                $isHoliday = $holiday !== null && !$isSun;
                $val = $empTs->get($d)?->nilai;

                // Tulis nilai
                if (!$isSun && $val !== null && $val !== '') {
                    $sheet->setCellValue($cellRef, $val);
                }

                // Tentukan warna latar berdasarkan nilai atau kondisi hari
                [$cellBg, $txtClr] = $this->dayCellColors($val, $isSun, $isHoliday, $rowBg);

                $sheet->getStyle($cellRef)->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $cellBg]],
                    'font' => ['size' => 8, 'color' => ['rgb' => $txtClr], 'bold' => is_numeric($val) && !$isSun],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
                ]);
            }

            // ── SUMMARY dengan FORMULA ──
            // Kolom hari pertama dan terakhir
            $firstDayCol = $this->colLetter($dayStartCol);
            $lastDayCol = $this->colLetter($dayStartCol + $daysInMonth - 1);
            $range = "{$firstDayCol}{$dataRow}:{$lastDayCol}{$dataRow}";

            // Total hadir = COUNTIF angka (bukan I/S/A/C)
            $colHadir = $this->colLetter($summaryStart);
            $sheet->setCellValue(
                $colHadir . $dataRow,
                "=SUMPRODUCT((ISNUMBER(VALUE({$range})))*({$range}<>\"\"))"
            );

            // Izin
            $colIzin = $this->colLetter($summaryStart + 1);
            $sheet->setCellValue($colIzin . $dataRow, "=COUNTIF({$range},\"I\")");

            // Sakit
            $colSakit = $this->colLetter($summaryStart + 2);
            $sheet->setCellValue($colSakit . $dataRow, "=COUNTIF({$range},\"S\")");

            // Alpha
            $colAlpa = $this->colLetter($summaryStart + 3);
            $sheet->setCellValue($colAlpa . $dataRow, "=COUNTIF({$range},\"A\")");

            // Cuti
            $colCuti = $this->colLetter($summaryStart + 4);
            $sheet->setCellValue($colCuti . $dataRow, "=COUNTIF({$range},\"C\")");

            // Style summary — warna hanya muncul kalau nilai > 0 (pakai conditional formatting)
            $summaryBgs = ['D4EDDA', 'BDD7EE', 'FFD966', 'FF7C80', 'C6EFCE'];
            $summaryTxts = ['155724', '1F497D', '7F6000', '9C0006', '276221'];
            foreach (range(0, 4) as $si) {
                $col = $this->colLetter($summaryStart + $si);
                $cellRef = $col . $dataRow;

                // Base style: putih/abu, teks muted
                $sheet->getStyle($cellRef)->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rowBg]],
                    'font' => ['size' => 8, 'bold' => $si === 0, 'color' => ['rgb' => 'BBBBBB']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
                ]);

                // Conditional: kalau > 0, beri warna
                $cf = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
                $cf->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_CELLIS);
                $cf->setOperatorType(\PhpOffice\PhpSpreadsheet\Style\Conditional::OPERATOR_GREATERTHAN);
                $cf->addCondition('0');
                $cf->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($summaryBgs[$si]);
                $cf->getStyle()->getFont()->getColor()->setRGB($summaryTxts[$si]);
                $cf->getStyle()->getFont()->setBold($si === 0);
                $sheet->getStyle($cellRef)->setConditionalStyles([$cf]);
            }

            // ── CONDITIONAL FORMATTING untuk kolom hari (I/S/A/C + angka) ──
            // Ini membuat kalau user input langsung di Excel, warna otomatis berubah
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $date = Carbon::create($tahun, $bulan, $d);
                if ($date->isSunday())
                    continue;  // Minggu skip
                $col = $this->colLetter($dayStartCol + $d - 1);
                $cellRef = $col . $dataRow;

                $this->applyDayConditionalFormatting($sheet, $cellRef);
            }

            $sheet->getRowDimension($dataRow)->setRowHeight(14);
            $dataRow++;
        }

        // ── BARIS KOSONG ──
        $sheet->getRowDimension($dataRow)->setRowHeight(10);
        $dataRow++;

        // ── TANDA TANGAN ──
        $ttdRow = $dataRow;
        $midCol = $this->colLetter($summaryStart - intdiv($daysInMonth, 2));

        $sheet->setCellValue('B' . $ttdRow, 'HR Project');
        $sheet->getStyle('B' . $ttdRow)->applyFromArray([
            'font' => ['size' => 9, 'italic' => true, 'color' => ['rgb' => '444444']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->setCellValue($midCol . $ttdRow, 'Project Manager');
        $sheet->getStyle($midCol . $ttdRow)->applyFromArray([
            'font' => ['size' => 9, 'italic' => true, 'color' => ['rgb' => '444444']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $dataRow++;
        $sheet->getRowDimension($dataRow)->setRowHeight(45);
        $dataRow++;

        $sheet->setCellValue('B' . $dataRow, $ttdHr);
        $sheet->getStyle('B' . $dataRow)->applyFromArray([
            'font' => ['size' => 10, 'bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '000000']]],
        ]);
        $sheet->setCellValue($midCol . $dataRow, $ttdPm);
        $sheet->getStyle($midCol . $dataRow)->applyFromArray([
            'font' => ['size' => 10, 'bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '000000']]],
        ]);

        $lastDataRow = $dataRow;

        // ── PRINT SETTINGS ──
        $sheet->setShowGridlines(false);
        $sheet->getPageSetup()->setPrintArea("A1:{$lastCol}{$lastDataRow}");
        $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A3);
        $sheet->getPageSetup()->setFitToPage(true);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(0.5)->setBottom(0.5)->setLeft(0.4)->setRight(0.4);
        $sheet->getHeaderFooter()->setOddHeader('')->setOddFooter('');
        $sheet->freezePane('E8');

        $projectNama = '';
        if ($projectId) {
            $proj = \App\Models\Project::find($projectId);
            $projectNama = $proj ? strtoupper($proj->kode ?? $proj->nama ?? '') : '';
        }
        $filename = $projectNama
            ? "Timesheet_{$projectNama}_{$bulanStr}_{$tahun}.xlsx"
            : "Timesheet_{$bulanStr}_{$tahun}.xlsx";
        $writer = new Xlsx($spreadsheet);

        return response()->stream(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    private function colLetter(int $n): string
    {
        $letter = '';
        while ($n > 0) {
            $n--;
            $letter = chr(65 + ($n % 26)) . $letter;
            $n = intdiv($n, 26);
        }
        return $letter;
    }

    /**
     * Warna latar/teks untuk sel header hari (row 6) di export Excel.
     * Minggu=merah, Libur=oranye, Sabtu=hijau, Normal=putih.
     * Ekstraksi murni dari export() — nilai warna & urutan prioritas kondisi tidak diubah.
     *
     * @return array{0: string, 1: string} [bgColor, txtColor]
     */
    private function dayHeaderColors(bool $isSun, bool $isHoliday, bool $isSat): array
    {
        if ($isSun) {
            return ['FF0000', 'FFFFFF'];
        } elseif ($isHoliday) {
            return ['F4A010', '3D1F00']; // oranye
        } elseif ($isSat) {
            return ['92D050', '276221'];
        }
        return ['FFFFFF', '000000']; // putih normal
    }

    /**
     * Warna latar/teks untuk sel data hari (I/S/A/C/angka jam) di export Excel.
     * Ekstraksi murni dari export() — nilai warna & urutan prioritas kondisi tidak diubah.
     *
     * @return array{0: string, 1: string} [cellBg, txtClr]
     */
    private function dayCellColors($val, bool $isSun, bool $isHoliday, string $rowBg): array
    {
        if ($isSun) {
            return ['AA0000', 'FFFFFF'];
        }
        if ($isHoliday && ($val === null || $val === '')) {
            return ['F4A010', '3D1F00'];
        }
        if ($val !== null && $val !== '') {
            $up = strtoupper((string) $val);
            if ($up === 'I') {
                return ['BDD7EE', '1F497D'];
            } elseif ($up === 'S') {
                return ['FFD966', '7F6000'];
            } elseif ($up === 'A') {
                return ['FF7C80', '9C0006'];
            } elseif ($up === 'C') {
                return ['C6EFCE', '276221'];
            } elseif (is_numeric($val)) {
                $n = (float) $val;
                if ($n >= 10) {
                    return ['CCFFCC', '276221'];
                } elseif ($n >= 8) {
                    return ['FFCCCC', '9C0006'];
                }
                return ['FF6666', 'FFFFFF'];
            }
            return [$rowBg, '000000'];
        }
        return [$rowBg, '000000'];
    }

    /**
     * Pasang conditional formatting Excel (I/S/A/C + rentang angka jam) pada satu sel hari,
     * supaya kalau user edit langsung di Excel warnanya otomatis mengikuti. Ekstraksi murni
     * dari export() — kondisi, warna, dan urutan style tidak diubah.
     */
    private function applyDayConditionalFormatting($sheet, string $cellRef): void
    {
        $styles = [];

        // I = Izin — biru muda
        $cfI = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
        $cfI->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_CELLIS);
        $cfI->setOperatorType(\PhpOffice\PhpSpreadsheet\Style\Conditional::OPERATOR_EQUAL);
        $cfI->addCondition('"I"');
        $cfI->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('BDD7EE');
        $cfI->getStyle()->getFont()->getColor()->setRGB('1F497D');
        $cfI->getStyle()->getFont()->setBold(true);
        $styles[] = $cfI;

        // S = Sakit — kuning
        $cfS = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
        $cfS->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_CELLIS);
        $cfS->setOperatorType(\PhpOffice\PhpSpreadsheet\Style\Conditional::OPERATOR_EQUAL);
        $cfS->addCondition('"S"');
        $cfS->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFD966');
        $cfS->getStyle()->getFont()->getColor()->setRGB('7F6000');
        $cfS->getStyle()->getFont()->setBold(true);
        $styles[] = $cfS;

        // A = Alpha — merah
        $cfA = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
        $cfA->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_CELLIS);
        $cfA->setOperatorType(\PhpOffice\PhpSpreadsheet\Style\Conditional::OPERATOR_EQUAL);
        $cfA->addCondition('"A"');
        $cfA->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FF7C80');
        $cfA->getStyle()->getFont()->getColor()->setRGB('9C0006');
        $cfA->getStyle()->getFont()->setBold(true);
        $styles[] = $cfA;

        // C = Cuti — hijau muda
        $cfC = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
        $cfC->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_CELLIS);
        $cfC->setOperatorType(\PhpOffice\PhpSpreadsheet\Style\Conditional::OPERATOR_EQUAL);
        $cfC->addCondition('"C"');
        $cfC->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('C6EFCE');
        $cfC->getStyle()->getFont()->getColor()->setRGB('276221');
        $cfC->getStyle()->getFont()->setBold(true);
        $styles[] = $cfC;

        // Angka >= 10 — hijau
        $cf10 = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
        $cf10->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_CELLIS);
        $cf10->setOperatorType(\PhpOffice\PhpSpreadsheet\Style\Conditional::OPERATOR_GREATERTHANOREQUAL);
        $cf10->addCondition('10');
        $cf10->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('CCFFCC');
        $cf10->getStyle()->getFont()->getColor()->setRGB('276221');
        $styles[] = $cf10;

        // Angka >= 8 dan < 10 — merah muda
        $cf8 = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
        $cf8->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_CELLIS);
        $cf8->setOperatorType(\PhpOffice\PhpSpreadsheet\Style\Conditional::OPERATOR_BETWEEN);
        $cf8->addCondition('8');
        $cf8->addCondition('9.99');
        $cf8->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFCCCC');
        $cf8->getStyle()->getFont()->getColor()->setRGB('9C0006');
        $styles[] = $cf8;

        // Angka > 0 dan < 8 — merah tua
        $cfLow = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
        $cfLow->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_CELLIS);
        $cfLow->setOperatorType(\PhpOffice\PhpSpreadsheet\Style\Conditional::OPERATOR_BETWEEN);
        $cfLow->addCondition('0.01');
        $cfLow->addCondition('7.99');
        $cfLow->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FF6666');
        $cfLow->getStyle()->getFont()->getColor()->setRGB('FFFFFF');
        $styles[] = $cfLow;

        $sheet->getStyle($cellRef)->setConditionalStyles($styles);
    }
}