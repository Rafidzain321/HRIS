<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ActivityLog;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserManagementController extends Controller
{
    public function index(Request $request)
    {
        $isSuperAdmin = auth()->user()->hasRole('super-admin');
        $pid          = $this->activeProjectId();

        $users = User::with(['roles', 'project'])->orderBy('name')->get()->map(fn($u) => [
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
            'plain_password' => $isSuperAdmin ? ($u->plain_password ?? '—') : null,
        ]);

        $roles    = Role::orderBy('name')->pluck('name');
        $projects = Project::orderBy('nama')->get(['id', 'kode', 'nama']);

        $positions = \App\Models\Position::orderBy('nama_jabatan')
            ->withCount('employees')->get()
            ->map(fn($p) => [
                'id'              => $p->id,
                'nama_jabatan'    => $p->nama_jabatan,
                'employees_count' => $p->employees_count,
            ]);

        // ── Activity Log — super admin lihat semua, project user lihat project sendiri ──
        $logQuery = \App\Models\ActivityLog::with('user')->orderByDesc('created_at')->limit(1000);
        if (!$isSuperAdmin && $pid) {
            // Filter log berdasarkan user yang ada di project yang sama
            $userIds = User::where('project_id', $pid)->pluck('id');
            $logQuery->whereIn('user_id', $userIds);
        }
        $logs = $logQuery->get()->map(fn($l) => [
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
            'users', 'roles', 'projects', 'positions', 'training_types', 'logs'
        ));
    }

    public function store(Request $request)
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);

        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'email'      => 'required|email|unique:users,email',
            'password'   => 'required|string|min:6',
            'role'       => 'required|exists:roles,name',
            'project_id' => 'nullable|exists:projects,id',
        ]);

        $user = User::create([
            'name'           => $data['name'],
            'email'          => $data['email'],
            'password'       => Hash::make($data['password']),
            'plain_password' => $data['password'],
            'project_id'     => $data['project_id'] ?? null,
            'is_active'      => true,
        ]);
        $user->assignRole($data['role']);

        ActivityLog::record('create', 'User', $user->name, "User baru ditambahkan dengan role {$data['role']}");
        return back()->with('success', "User {$user->name} berhasil ditambahkan.");
    }

    public function update(Request $request, User $user)
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);

        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'email'      => "required|email|unique:users,email,{$user->id}",
            'password'   => 'nullable|string|min:6',
            'role'       => 'required|exists:roles,name',
            'project_id' => 'nullable|exists:projects,id',
        ]);

        $user->update([
            'name'       => $data['name'],
            'email'      => $data['email'],
            'project_id' => $data['project_id'] ?? null,
            ...(isset($data['password']) && $data['password']
                ? ['password' => Hash::make($data['password']), 'plain_password' => $data['password']]
                : []),
        ]);
        $user->syncRoles([$data['role']]);

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

    public function logout(Request $request)
    {
        ActivityLog::record('logout', 'Auth', auth()->user()?->name);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }
}