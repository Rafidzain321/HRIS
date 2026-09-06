<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ActivityLog;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserManagementController extends Controller
{
    public function index(Request $request)
    {
        $isSuperAdmin = auth()->user()->hasRole('super-admin');
        $pid          = $this->activeProjectId();

        // ── User list cuma untuk super-admin — user lain (project-user, viewer, dst) tidak boleh
        // ikut menerima daftar akun/izin orang lain lewat props Inertia, meskipun tab-nya sudah
        // disembunyikan di frontend (props tetap terkirim di response).
        $users = $isSuperAdmin
            ? User::with(['roles', 'project', 'permissions'])->orderBy('name')->get()->map(fn($u) => [
                'id'             => $u->id,
                'name'           => $u->name,
                'email'          => $u->email,
                'role'           => $u->roles->first()?->name ?? '—',
                'project_id'     => $u->project_id,
                'project_ids'    => $u->project_ids ? json_decode($u->project_ids, true) : null,
                'project_nama'   => $u->project?->nama ?? 'Semua',
                'is_active'      => $u->is_active ?? true,
                'created_at'     => $u->created_at->format('d M Y'),
                'last_login'     => $u->last_login_at?->format('d M Y H:i') ?? '—',
                'plain_password' => $u->plain_password ?? '—',
                'permissions'    => $u->hasRole('super-admin') ? null : $u->permissions->pluck('name'),
            ])
            : collect();

        $roles    = Role::orderBy('name')->pluck('name');
        $projects = $isSuperAdmin
            ? Project::withCount('employees')->orderBy('nama')->get()->map(fn ($p) => [
                'id'             => $p->id,
                'kode'           => $p->kode,
                'nama'           => $p->nama,
                'lokasi'         => $p->lokasi,
                'tipe_timesheet' => $p->tipe_timesheet,
                'tipe_gaji'      => $p->tipe_gaji,
                'warna'          => $p->warna,
                'is_active'      => $p->is_active,
                'employees_count'=> $p->employees_count,
            ])
            : Project::where('is_active', true)->orderBy('nama')->get(['id', 'kode', 'nama']);
        $menus    = collect(config('menus'))->map(fn ($m, $key) => ['key' => $key, ...$m])->values();

        $positions = \App\Models\Position::orderBy('nama_jabatan')
            ->withCount('employees')->get()
            ->map(fn($p) => [
                'id'              => $p->id,
                'nama_jabatan'    => $p->nama_jabatan,
                'employees_count' => $p->employees_count,
            ]);

        // ── Activity Log — super admin lihat semua, project user lihat project ASAL sendiri saja.
        // Sengaja pakai project_id (home project, tetap), BUKAN activeProjectId() (project yang lagi
        // di-switch) — supaya user multi-project (Budi/Efendi/Ali) yang lagi lihat data project lain
        // tetap cuma lihat log aktivitas HO, bukan ikut lihat log aktivitas project yang sedang dilihat.
        $logQuery = \App\Models\ActivityLog::with('user')->orderByDesc('created_at')->limit(1000);
        if (!$isSuperAdmin) {
            $ownProjectId = auth()->user()->project_id;
            if ($ownProjectId) {
                $userIds = User::where('project_id', $ownProjectId)->pluck('id');
                $logQuery->whereIn('user_id', $userIds);
            }
        }
        // Beberapa akun sengaja tidak boleh lihat Log Aktivitas sama sekali (mis. Nedriyanto,
        // Rahmat Sjukri) — sama seperti restrict_payroll, dikunci per akun lewat kolom ini.
        $logs = (!$isSuperAdmin && auth()->user()->restrict_activity_log)
            ? collect()
            : $logQuery->get()->map(fn($l) => [
                'id'          => $l->id,
                'user_name'   => $l->user?->name ?? 'System',
                'user_project'=> $l->user?->project?->nama ?? '—',
                'action'      => $l->action,
                'module'      => $l->module,
                'target_name' => $l->target_name,
                'description' => $l->description,
                'ip_address'  => $l->ip_address,
                'created_at'  => $l->created_at->format('d M Y H:i:s'),
            ]);

        $training_types = \App\Models\TrainingType::orderBy('urutan')
            ->withCount('trainings')->get()
            ->map(fn($t) => [
                'id'                 => $t->id,
                'nama'               => $t->nama,
                'deskripsi'          => $t->deskripsi,
                'masa_berlaku_tahun' => $t->masa_berlaku_tahun,
                'masa_berlaku_label' => $t->masa_berlaku_label,
                'has_nilai'          => $t->has_nilai,
                'has_expired'        => $t->has_expired,
                'trainings_count'    => $t->trainings_count,
            ]);

        return Inertia::render('Pengaturan/Index', compact(
            'users', 'roles', 'projects', 'positions', 'training_types', 'logs', 'menus'
        ));
    }

    // menu+action ('view'|'edit') -> nama permission Spatie ("view-karyawan" dst), tervalidasi terhadap config/menus.php.
    private function permissionNames(array $selected): array
    {
        $menus = config('menus');
        $names = [];
        foreach ($selected as $item) {
            $menu   = $item['menu']   ?? null;
            $action = $item['action'] ?? null;
            if (!$menu || !isset($menus[$menu])) continue;
            if ($action === 'view') $names[] = "view-{$menu}";
            if ($action === 'edit' && $menus[$menu]['edit']) $names[] = "edit-{$menu}";
        }
        return array_unique($names);
    }

    public function store(Request $request)
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);

        $data = $request->validate([
            'name'                  => 'required|string|max:100',
            'email'                 => 'required|email|unique:users,email',
            'password'              => 'required|string|min:6',
            'role'                  => 'required|exists:roles,name',
            'project_id'            => 'nullable|exists:projects,id',
            'project_ids'           => 'nullable|array',
            'project_ids.*'         => 'exists:projects,id',
            'permissions'           => 'array',
            'permissions.*.menu'    => 'required|string',
            'permissions.*.action'  => 'required|in:view,edit',
        ]);

        $user = User::create([
            'name'           => $data['name'],
            'email'          => $data['email'],
            'password'       => Hash::make($data['password']),
            'plain_password' => $data['password'],
            'project_id'     => $data['project_id'] ?? null,
            'project_ids'    => !empty($data['project_ids']) ? json_encode(array_map('intval', $data['project_ids'])) : null,
            'is_active'      => true,
        ]);
        $user->assignRole($data['role']);
        if ($data['role'] !== 'super-admin') {
            $user->syncPermissions($this->permissionNames($data['permissions'] ?? []));
        }

        ActivityLog::record('create', 'User', $user->name, "User baru ditambahkan dengan role {$data['role']}");
        return back()->with('success', "User {$user->name} berhasil ditambahkan.");
    }

    public function update(Request $request, User $user)
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);

        $data = $request->validate([
            'name'                  => 'required|string|max:100',
            'email'                 => "required|email|unique:users,email,{$user->id}",
            'password'              => 'nullable|string|min:6',
            'role'                  => 'required|exists:roles,name',
            'project_id'            => 'nullable|exists:projects,id',
            'project_ids'           => 'nullable|array',
            'project_ids.*'         => 'exists:projects,id',
            'permissions'           => 'array',
            'permissions.*.menu'    => 'required|string',
            'permissions.*.action'  => 'required|in:view,edit',
        ]);

        $user->update([
            'name'        => $data['name'],
            'email'       => $data['email'],
            'project_id'  => $data['project_id'] ?? null,
            'project_ids' => !empty($data['project_ids']) ? json_encode(array_map('intval', $data['project_ids'])) : null,
            ...(isset($data['password']) && $data['password']
                ? ['password' => Hash::make($data['password']), 'plain_password' => $data['password']]
                : []),
        ]);
        $user->syncRoles([$data['role']]);
        $user->syncPermissions($data['role'] === 'super-admin' ? [] : $this->permissionNames($data['permissions'] ?? []));

        ActivityLog::record('update', 'User', $user->name, "Data user diperbarui");
        return back()->with('success', "User {$user->name} berhasil diperbarui.");
    }

    // ── Super admin reset password user lain ──
    public function resetPassword(Request $request, User $user)
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);

        $data = $request->validate([
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user->update(['password' => Hash::make($data['password']), 'plain_password' => $data['password']]);
        ActivityLog::record('update', 'User', $user->name, "Password di-reset oleh super admin");
        return back()->with('success', "Password {$user->name} berhasil direset.");
    }

    // ── User ganti password sendiri ──
    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required|string',
            'password'         => 'required|string|min:6|confirmed',
        ]);

        $user = auth()->user();

        if (!Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Password lama tidak sesuai.']);
        }

        $user->update(['password' => Hash::make($data['password']), 'plain_password' => $data['password']]);
        ActivityLog::record('update', 'User', $user->name, "Ganti password sendiri");
        return back()->with('success', "Password berhasil diubah.");
    }

    public function toggleActive(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Tidak bisa menonaktifkan akun sendiri.');
        }
        $user->update(['is_active' => !$user->is_active]);
        $status = $user->is_active ? 'diaktifkan' : 'dinonaktifkan';
        ActivityLog::record('update', 'User', $user->name, "User {$status}");
        return back()->with('success', "User {$user->name} berhasil {$status}.");
    }

    public function destroy(User $user)
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Tidak bisa menghapus akun sendiri.');
        }
        $nama = $user->name;
        $user->delete();
        ActivityLog::record('delete', 'User', $nama, "User dihapus dari sistem");
        return back()->with('success', "User {$nama} berhasil dihapus.");
    }

}