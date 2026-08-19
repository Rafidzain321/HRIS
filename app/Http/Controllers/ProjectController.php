<?php
// app/Http/Controllers/ProjectController.php
namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    private function assertSuperAdmin(): void
    {
        if (!auth()->user()?->hasRole('super-admin')) {
            abort(403, 'Cuma super-admin yang boleh mengelola master project.');
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

        ActivityLog::record('create', 'Project', $project->nama, "Project baru: {$project->nama} ({$project->kode})");

        return back()->with('success', "Project {$project->nama} berhasil ditambahkan.");
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
        $project->update($data);

        ActivityLog::record('update', 'Project', $project->nama, "Update project: {$project->nama} ({$project->kode})");

        return back()->with('success', "Project {$project->nama} berhasil diperbarui.");
    }

    public function toggleActive(Project $project)
    {
        $this->assertSuperAdmin();

        $project->update(['is_active' => !$project->is_active]);

        ActivityLog::record('update', 'Project', $project->nama, $project->is_active ? "Project diaktifkan: {$project->nama}" : "Project dinonaktifkan: {$project->nama}");

        return back()->with('success', "Project {$project->nama} berhasil " . ($project->is_active ? 'diaktifkan' : 'dinonaktifkan') . '.');
    }
}
