<?php
// app/Http/Controllers/ProjectController.php
namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    private function assertSuperAdmin(): void
    {
        if (!auth()->user()?->hasRole('super-admin')) {
            abort(403, 'Cuma super-admin yang boleh mengelola master kantor.');
        }
    }

    public function store(Request $request)
    {
        $this->assertSuperAdmin();

        $data = $request->validate([
            'kode'           => 'required|string|max:20|unique:projects,kode',
            'nama'           => 'required|string|max:255',
            'lokasi'         => 'nullable|string|max:255',
            'tipe_timesheet' => 'required|in:7jam,8jam',
            'tipe_gaji'      => 'required|in:giam,md,ho',
            'warna'          => 'nullable|string|max:10',
        ]);

        $data['kode']      = strtolower($data['kode']);
        $data['warna']     = $data['warna'] ?: '#3A8FE0';
        $data['is_active'] = true;

        $project = Project::create($data);

        ActivityLog::record('create', 'Kantor', $project->nama, "Kantor baru: {$project->nama} ({$project->kode})");

        return back()->with('success', "Kantor {$project->nama} berhasil ditambahkan.");
    }

    public function update(Request $request, Project $project)
    {
        $this->assertSuperAdmin();

        $data = $request->validate([
            'kode'           => ['required', 'string', 'max:20', Rule::unique('projects', 'kode')->ignore($project->id)],
            'nama'           => 'required|string|max:255',
            'lokasi'         => 'nullable|string|max:255',
            'tipe_timesheet' => 'required|in:7jam,8jam',
            'tipe_gaji'      => 'required|in:giam,md,ho',
            'warna'          => 'nullable|string|max:10',
        ]);

        $data['kode'] = strtolower($data['kode']);
        $lama = $project->only(array_keys($data));
        $project->update($data);

        // Catat kolom apa saja yang berubah, mis. "tipe_gaji: giam → md".
        $perubahan = collect($project->getChanges())->except('updated_at')
            ->map(fn($baru, $kolom) => "{$kolom}: " . ($lama[$kolom] ?? '-') . ' → ' . ($baru ?? '-'))
            ->implode(', ');
        ActivityLog::record('update', 'Kantor', $project->nama,
            "Update kantor: {$project->nama} ({$project->kode})" . ($perubahan ? " — {$perubahan}" : ' — tidak ada perubahan'));

        return back()->with('success', "Kantor {$project->nama} berhasil diperbarui.");
    }

    public function toggleActive(Project $project)
    {
        $this->assertSuperAdmin();

        $project->update(['is_active' => !$project->is_active]);

        ActivityLog::record('update', 'Kantor', $project->nama, $project->is_active ? "Kantor diaktifkan: {$project->nama}" : "Kantor dinonaktifkan: {$project->nama}");

        return back()->with('success', "Kantor {$project->nama} berhasil " . ($project->is_active ? 'diaktifkan' : 'dinonaktifkan') . '.');
    }

    public function destroy(Project $project)
    {
        $this->assertSuperAdmin();

        // Karyawan di project ini pakai FK "SET NULL" ke projects — kalau dibiarkan, hapus
        // project akan diam-diam melepas karyawan itu jadi tanpa project sama sekali (bukan
        // error, tapi data rusak senyap). Wajib dicegah dan dikasih peringatan yang jelas.
        $employeeCount = $project->employees()->count();
        if ($employeeCount > 0) {
            return back()->with('error', "Kantor {$project->nama} tidak bisa dihapus karena masih ada {$employeeCount} karyawan terdaftar di kantor ini. Pindahkan atau nonaktifkan karyawan tersebut terlebih dahulu.");
        }

        // User (akun login) yang masih terhubung ke project ini — baik sebagai project utama
        // maupun salah satu project pada akun multi-project — juga wajib dicek dulu.
        $userCount = User::where('project_id', $project->id)
            ->orWhereJsonContains('project_ids', $project->id)
            ->count();
        if ($userCount > 0) {
            return back()->with('error', "Kantor {$project->nama} tidak bisa dihapus karena masih ada {$userCount} akun user yang terhubung ke kantor ini. Pindahkan akun tersebut ke kantor lain terlebih dahulu.");
        }

        $nama = $project->nama;
        $kode = $project->kode;

        try {
            $project->delete();
        } catch (QueryException $e) {
            // Jaga-jaga kalau masih ada relasi lain yang menahan (mis. histori transfer karyawan
            // yang FK-nya RESTRICT) — jangan biarkan error database mentah tampil ke user.
            return back()->with('error', "Kantor {$nama} tidak bisa dihapus karena masih terhubung dengan data lain (histori transfer karyawan, timesheet, dll).");
        }

        ActivityLog::record('delete', 'Kantor', $nama, "Kantor dihapus: {$nama} ({$kode})");

        return back()->with('success', "Kantor {$nama} berhasil dihapus.");
    }
}