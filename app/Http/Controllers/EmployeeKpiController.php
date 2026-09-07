<?php
// app/Http/Controllers/EmployeeKpiController.php
namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\EmployeeGoal;
use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;

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
        return [(int) $user->employee_id, ...$bawahanIds];
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

    private function serializeGoal(EmployeeGoal $g): array
    {
        return [
            'id'                => $g->id,
            'employee_id'       => $g->employee_id,
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
        ]);
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
            'is_self_only' => $isSelfOnly,
        ]);
    }

    public function storeGoal(Request $request)
    {
        $data = $request->validate([
            'employee_id'      => 'required|exists:employees,id',
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
        if (!$this->canEditKpiFor($goal->employee_id)) {
            return response()->json(['ok' => false, 'message' => 'Kamu tidak memiliki akses untuk mengisi progress goal ini.'], 403);
        }

        $data = $request->validate([
            'progress_sekarang' => 'required|numeric',
            'catatan'           => 'nullable|string|max:1000',
        ]);

        $goal->update([
            'progress_sekarang' => $data['progress_sekarang'],
            'catatan'           => $data['catatan'] ?? $goal->catatan,
            'diperbarui_oleh'   => auth()->user()?->name,
        ]);

        return response()->json(['ok' => true, 'goal' => $this->serializeGoal($goal->fresh())]);
    }

    public function destroyGoal(EmployeeGoal $goal)
    {
        if (!$this->canEditKpiFor($goal->employee_id)) {
            return response()->json(['ok' => false, 'message' => 'Kamu tidak memiliki akses untuk menghapus goal ini.'], 403);
        }

        $goal->delete();

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
