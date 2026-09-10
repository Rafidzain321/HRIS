<?php

namespace App\Http\Middleware;

use App\Models\Employee;
use App\Models\EmployeeGoal;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $permissions = $user
            ? ($user->hasRole('super-admin')
                ? \Spatie\Permission\Models\Permission::pluck('name')
                : $user->getAllPermissions()->pluck('name'))
            : collect();

        // Sama persis dengan logika di CheckMenuPermission middleware — dipakai frontend
        // supaya tombol Tambah/Edit/Hapus otomatis disembunyikan (bukan cuma gagal 403)
        // saat user multi-project sedang melihat project selain project asalnya.
        $isProjectReadonly = false;
        if ($user && !$user->hasRole('super-admin') && !$user->hasRole('viewer')) {
            $projectIds = $user->project_ids ? json_decode($user->project_ids, true) : null;
            if (is_array($projectIds) && count($projectIds) > 1) {
                $activeProjectId = session('active_project_kode') ?: ($projectIds[0] ?? null);
                $isProjectReadonly = (int) $activeProjectId !== (int) $user->project_id;
            }
        }

        // Badge "pending review" KPI di sidebar — gabungan dari 2 sumber, keduanya cuma yang
        // progress-nya belum pernah diisi sama sekali (masih = baseline):
        //  1) goal yang menunjuk user ini sebagai reviewer yang ditugaskan bebas (reviewer_id)
        //  2) goal milik bawahan langsungnya (atasan_id) — supaya manajer otomatis tahu ada
        //     goal timnya yang belum diupdate, walau tidak ada penugasan reviewer eksplisit.
        // Khusus akun self-input (bukan HR/super-admin/GM-Direktur — mereka sudah punya
        // tab Dashboard KPI buat pantauan menyeluruh, badge personal begini kurang relevan).
        $kpiPendingReview = 0;
        if ($user && $user->employee_id && !$user->hasRole('super-admin') && !$user->can('edit-kpi') && !$user->can('view-all-kpi')) {
            $bawahanIds = Employee::where('atasan_id', $user->employee_id)->pluck('id')->toArray();
            $kpiPendingReview = EmployeeGoal::where('aktif', true)
                ->whereColumn('progress_sekarang', 'baseline')
                ->where(function ($q) use ($user, $bawahanIds) {
                    $q->where('reviewer_id', $user->employee_id);
                    if (!empty($bawahanIds)) {
                        $q->orWhereIn('employee_id', $bawahanIds);
                    }
                })
                ->count();
        }

        return array_merge(parent::share($request), [
            'kpi_pending_review' => $kpiPendingReview,
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->roles->first()?->name,
                    'project_id' => $user->project_id,
                    'project_ids' => $user->project_ids ? json_decode($user->project_ids, true) : null,
                    'project' => $user->project ? [
                        'id' => $user->project->id,
                        'kode' => $user->project->kode,
                        'nama' => $user->project->nama,
                        'warna' => $user->project->warna,
                    ] : null,
                    'can' => [
                        'is_super_admin' => $user->hasRole('super-admin'),
                        'is_viewer' => $user->hasRole('viewer'),
                        'is_project_user' => $user->hasRole('project-user'),
                        'is_project_readonly' => $isProjectReadonly,
                        'restrict_payroll' => (bool) $user->restrict_payroll,
                        'restrict_activity_log' => (bool) $user->restrict_activity_log,
                    ],
                    'permissions' => $permissions,
                ] : null,
            ],
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
                'import_result' => session('import_result'),
            ],
            'active_project_id' => session('active_project_kode'),
            'projects' => \App\Models\Project::where('is_active', true)
                ->orderBy('nama')
                ->get(['id', 'kode', 'nama', 'warna', 'tipe_gaji'])
                ->toArray(),
        ]);
    }
}
