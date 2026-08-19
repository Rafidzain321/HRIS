<?php
namespace App\Http\Controllers;

// DashboardController.php
use App\Models\Employee;
use Carbon\Carbon;
use Inertia\Inertia;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $today = Carbon::today();
        $in30  = Carbon::today()->addDays(30);

        $user = auth()->user();
        if ($user->hasRole('super-admin')) {
            if ($request->has('project')) {
                $kode = $request->get('project');
                if ($kode === 'all') {
                    session()->forget('active_project_kode');
                } else {
                    $proj = \App\Models\Project::where('kode', $kode)->first();
                    if ($proj) session(['active_project_kode' => $proj->id]);
                }
            }
            $activeProjectId = session('active_project_kode');
        } else {
            if ($request->has('project')) {
                $kode = $request->get('project');
                $proj = \App\Models\Project::where('kode', $kode)->first();
                if ($proj) session(['active_project_kode' => $proj->id]);
            }
            $activeProjectId = $this->activeProjectId();
        }

        $nkId = \App\Models\Project::where('kode','NK')->value('id');
        $pf = fn($q) => $activeProjectId
            ? $q->where('project_id', $activeProjectId)
            : $q->where('project_id', '!=', $nkId);

        $projectInfo = $activeProjectId ? \App\Models\Project::find($activeProjectId, ['id', 'kode', 'nama', 'tipe_gaji']) : null;
        $isHo = $projectInfo && $projectInfo->tipe_gaji === 'ho';

        $stats = [
            'total_karyawan' => $pf(Employee::aktif())->count(),
            'sim_expired'    => $pf(Employee::simExpired())->count(),
            'sim_warning'    => $pf(Employee::simWarning())->count(),
            'mcu_expired'    => $pf(Employee::mcuExpired())->count(),
            'badge_expired'  => $pf(Employee::badgeExpired())->count(),
            'badge_warning'  => $pf(Employee::badgeWarning())->count(),
            'sio_expired'    => $pf(Employee::aktif())->whereNotNull('expire_sio')->where('expire_sio','<',$today)->count(),
            'kp_ada'         => $pf(Employee::aktif())->where('status_kp','KP has been exist')->count(),
            'ccpm_aktif'     => $pf(Employee::aktif())->where('ccpm','AKTIF')->count(),
            'mcu_ok'         => $pf(Employee::aktif())->where('status_mcu','OK')->count(),
        ];

        // HO tidak punya compliance (SIM/MCU/Badge) — tampilkan breakdown unit & jabatan terbanyak sebagai gantinya.
        if ($isHo) {
            $hoEmployees = $pf(Employee::aktif())->with(['hoDetail', 'position'])->get();
            $stats['ho_unit'] = [
                'HO-1' => $hoEmployees->filter(fn($e) => $e->hoDetail?->unit === 'HO-1')->count(),
                'HO-2' => $hoEmployees->filter(fn($e) => $e->hoDetail?->unit === 'HO-2')->count(),
            ];
            $stats['ho_top_jabatan'] = $hoEmployees
                ->filter(fn($e) => $e->position?->nama_jabatan)
                ->groupBy(fn($e) => $e->position->nama_jabatan)
                ->map(fn($g, $label) => ['label' => $label, 'val' => $g->count()])
                ->sortByDesc('val')
                ->values()
                ->first();
        }

        $jabatan_stats = $pf(Employee::aktif())
            ->selectRaw('(SELECT nama_jabatan FROM positions WHERE positions.id = employees.position_id) as jabatan, COUNT(*) as total')
            ->groupBy('position_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // ── Ambil semua: warning (30 hari ke depan) + expired (90 hari ke belakang) ──
        $in90ago = $today->copy()->subDays(90);

        $allAlertEmp = $pf(Employee::aktif())
            ->where(fn($q) => $q
                ->whereBetween('expired_sim',   [$in90ago, $in30])
                ->orWhereBetween('exp_mcu',      [$in90ago, $in30])
                ->orWhereBetween('expire_badge', [$in90ago, $in30])
            )
            ->with('position')->get();


        $mapEmployee = fn($e) => [
            'id'               => $e->id,
            'id_badge'         => $e->id_badge,
            'nama'             => $e->nama_lengkap,
            'jabatan'          => $e->position?->nama_jabatan ?? '-',
            'sim_status'       => $e->sim_status,
            'mcu_status'       => $e->mcu_status,
            'badge_status'     => $e->badge_status,
            'expired_sim_fmt'  => $e->expired_sim?->format('d M Y'),
            'exp_mcu_fmt'      => $e->exp_mcu?->format('d M Y'),
            'expire_badge_fmt' => $e->expire_badge?->format('d M Y'),
            'sisa_sim'         => $e->expired_sim   ? (int) $today->diffInDays($e->expired_sim,   false) : null,
            'sisa_mcu'         => $e->exp_mcu        ? (int) $today->diffInDays($e->exp_mcu,        false) : null,
            'sisa_badge'       => $e->expire_badge   ? (int) $today->diffInDays($e->expire_badge,   false) : null,
        ];

        // Helper: ambil sisa hari paling urgent dari karyawan
        $getSisaUrgent = fn($e) => min(
            ($e['sisa_sim']   !== null && in_array($e['sim_status'],   ['warning','expired'])) ? $e['sisa_sim']   : 9999,
            ($e['sisa_mcu']   !== null && in_array($e['mcu_status'],   ['warning','expired'])) ? $e['sisa_mcu']   : 9999,
            ($e['sisa_badge'] !== null && in_array($e['badge_status'], ['warning','expired'])) ? $e['sisa_badge'] : 9999,
        );

        $alert_employees = $allAlertEmp
            ->map($mapEmployee)
            ->filter(fn($e) =>
                $e['sim_status']==='warning'   || $e['sim_status']==='expired'   ||
                $e['mcu_status']==='warning'   || $e['mcu_status']==='expired'   ||
                $e['badge_status']==='warning' || $e['badge_status']==='expired'
            )
            ->sort(function($a, $b) use ($getSisaUrgent) {
                $sisaA = $getSisaUrgent($a);
                $sisaB = $getSisaUrgent($b);

                $aExpired = $sisaA < 0;
                $bExpired = $sisaB < 0;

                if (!$aExpired && $bExpired) return -1;
                if ($aExpired && !$bExpired) return 1;
                return $sisaA <=> $sisaB;
            })
            ->values();

        // ── PENSIUN: usia >= 53 tahun ─────────────────────────────
        $pensiunDate56  = $today->copy()->subYears(56);
        $pensiunDate53  = $today->copy()->subYears(53);

        $pensiun_employees = $pf(Employee::aktif())
            ->whereNotNull('tanggal_lahir')
            ->where('tanggal_lahir', '<=', $pensiunDate53)
            ->with('position')
            ->orderBy('tanggal_lahir')
            ->get()
            ->map(fn($e) => [
                'id'            => $e->id,
                'id_badge'      => $e->id_badge,
                'nama_lengkap'  => $e->nama_lengkap,
                'jabatan'       => $e->position?->nama_jabatan ?? '-',
                'tanggal_lahir' => $e->tanggal_lahir?->format('d M Y'),
                'usia'          => $e->tanggal_lahir ? (int) $e->tanggal_lahir->diffInYears($today) : null,
                'sudah_56'      => $e->tanggal_lahir ? $e->tanggal_lahir->lte($pensiunDate56) : false,
                'dokumen_label' => 'Pensiun 56 Thn',
            ]);

        // ═══════════════════════════════════════════════════════════
        // CHART DATA — Filter by project berdasarkan hak akses user
        // ═══════════════════════════════════════════════════════════

        // Tentukan project_ids yang boleh dilihat user
        if ($activeProjectId) {
            // Sedang filter project tertentu
            $visibleProjectIds = [$activeProjectId];
        } else {
            // "Semua project" — batasi berdasarkan hak akses user
            if ($user->hasRole('super-admin')) {
                // Super-admin lihat semua kecuali NK (sesuai pola $pf di atas)
                $visibleProjectIds = \App\Models\Project::where('id', '!=', $nkId)
                    ->pluck('id')->toArray();
            } else {
                // User biasa: lihat sesuai project_ids atau project_id-nya
                $visibleProjectIds = $user->project_ids
                    ? json_decode($user->project_ids, true)
                    : [$user->project_id];
            }
        }

        $isMultiProject = count($visibleProjectIds) > 1;

        // ── Chart 1: Jumlah Karyawan Aktif per Project (semua project) ──
        $karyawanPerProject = \App\Models\Project::leftJoin('employees', function ($join) {
                $join->on('employees.project_id', '=', 'projects.id')
                    ->where('employees.status', 'aktif');
            })
            ->select('projects.id', 'projects.nama', 'projects.kode',
                    \DB::raw('COUNT(employees.id) as total'))
            ->groupBy('projects.id', 'projects.nama', 'projects.kode')
            ->orderBy('projects.id')
            ->get()
            ->map(fn($p) => [
                'project' => $p->kode ?: $p->nama,
                'total'   => (int) $p->total,
            ]);

        // ── Chart 2: Total Pengeluaran Gaji 12 Bulan Terakhir ──
        $namaBulan = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];

        $projectsMap = \App\Models\Project::whereIn('id', $visibleProjectIds)
            ->pluck('kode', 'id');

        $startDate = \Carbon\Carbon::now()->subMonths(11)->startOfMonth();
        $startKey  = $startDate->year * 100 + $startDate->month;

        $rawGaji = \App\Models\EmployeePayroll::query()
            ->join('employees', 'employees.id', '=', 'employee_payroll.employee_id')
            ->whereIn('employees.project_id', $visibleProjectIds)
            ->whereRaw('(employee_payroll.tahun * 100 + employee_payroll.bulan) >= ?', [$startKey])
            ->groupBy('employee_payroll.tahun', 'employee_payroll.bulan', 'employees.project_id')
            ->selectRaw('
                employee_payroll.tahun,
                employee_payroll.bulan,
                employees.project_id,
                SUM(employee_payroll.gaji_bersih) as total
            ')
            ->get();

        $gajiIndex = [];
        foreach ($rawGaji as $r) {
            $key = $r->tahun * 100 + $r->bulan;
            $gajiIndex[$key][$r->project_id] = (float) $r->total;
        }

        $payrollCtrl = app(\App\Http\Controllers\PayrollController::class);
        $pengeluaranGaji = [];

        for ($i = 11; $i >= 0; $i--) {
            $d = \Carbon\Carbon::now()->subMonths($i);
            $bulan = $d->month;
            $tahun = $d->year;
            $key   = $tahun * 100 + $bulan;

            $row = [
                'label' => $namaBulan[$bulan - 1] . ' ' . substr($tahun, -2),
                'total' => 0,
            ];

            foreach ($visibleProjectIds as $pid) {
                $kode = $projectsMap[$pid] ?? "Project{$pid}";

                if (isset($gajiIndex[$key][$pid])) {
                    $totalPerProject = $gajiIndex[$key][$pid];
                } else {
                    try {
                        $built = $payrollCtrl->buildPayrollRows($tahun, $bulan, $pid);
                        $totalPerProject = $built['total_gaji_bersih'] ?? 0;
                    } catch (\Throwable $e) {
                        $totalPerProject = 0;
                    }
                }

                $row[$kode] = (float) $totalPerProject;
                $row['total'] += (float) $totalPerProject;
            }

            $pengeluaranGaji[] = $row;
        }

        $projectKeys = array_values($projectsMap->toArray());

        return Inertia::render('Dashboard/Index', [
            'stats'              => $stats,
            'jabatan_stats'      => $jabatan_stats,
            'alert_employees'    => $alert_employees,
            'pensiun_employees'  => $pensiun_employees,
            'active_project_id'  => $activeProjectId,
            'project_info'       => $projectInfo,
            'karyawanPerProject' => $karyawanPerProject,
            'pengeluaranGaji'    => $pengeluaranGaji,
            'projectKeys'        => $projectKeys,
            'isMultiProject'     => $isMultiProject,
        ]);
    }
}