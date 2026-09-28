<?php
namespace App\Http\Controllers;

use App\Models\ClientProject;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

// "Data Project" — project riil (kode kontrak/pekerjaan, mis. "AKM-PP"), beda dari Project
// (kantor/entitas payroll). Tiap project dihubungkan ke satu kantor, bisa diaktif/nonaktifkan.
// Dipakai buat penanda karyawan mana pegang project mana (many-to-many, lihat Employee::clientProjects()).
class ClientProjectController extends Controller
{
    public function store(Request $request)
    {
        if (!$this->isAdminSettings()) abort(403);

        $data = $request->validate([
            'kode'       => 'required|string|max:50|unique:client_projects,kode',
            'project_id' => 'required|exists:projects,id',
        ], ['project_id.required' => 'Kantor wajib dipilih.']);
        $cp = ClientProject::create($data);
        ActivityLog::record('create', 'Data Project', $cp->kode, "Kantor: {$cp->kantor?->nama}");
        return back()->with('success', "Project \"{$cp->kode}\" berhasil ditambahkan.");
    }

    public function update(Request $request, ClientProject $clientProject)
    {
        if (!$this->isAdminSettings()) abort(403);

        $data = $request->validate([
            'kode'       => "required|string|max:50|unique:client_projects,kode,{$clientProject->id}",
            'project_id' => 'required|exists:projects,id',
        ], ['project_id.required' => 'Kantor wajib dipilih.']);
        $old       = $clientProject->kode;
        $oldKantor = $clientProject->kantor?->nama ?? '-';
        $clientProject->update($data);
        $newKantor = $clientProject->fresh('kantor')->kantor?->nama ?? '-';
        ActivityLog::record('update', 'Data Project', $data['kode'], "Kode: \"{$old}\" -> \"{$data['kode']}\", Kantor: {$oldKantor} -> {$newKantor}");
        return back()->with('success', 'Project berhasil diperbarui.');
    }

    // "Edit Kantor → Pilih Project": set sekaligus project apa saja milik satu kantor.
    // Project yang dicentang pindah ke kantor ini; yang tadinya milik kantor ini tapi tidak dicentang dilepas.
    public function syncKantor(Request $request, \App\Models\Project $project)
    {
        if (!$this->isAdminSettings()) abort(403);

        $data = $request->validate([
            'client_project_ids'   => 'nullable|array',
            'client_project_ids.*' => 'exists:client_projects,id',
        ]);
        $ids = array_map('intval', $data['client_project_ids'] ?? []);

        $lepas = ClientProject::where('project_id', $project->id)->whereNotIn('id', $ids)->pluck('kode');
        ClientProject::where('project_id', $project->id)->whereNotIn('id', $ids)->update(['project_id' => null]);
        ClientProject::whereIn('id', $ids)->update(['project_id' => $project->id]);

        $kodes = ClientProject::whereIn('id', $ids)->orderBy('kode')->pluck('kode')->implode(', ');
        ActivityLog::record('update', 'Data Project', $project->nama,
            'Project kantor diatur: ' . ($kodes ?: '(kosong)') . ($lepas->isNotEmpty() ? ' | Dilepas: ' . $lepas->implode(', ') : ''));
        return back()->with('success', "Project kantor {$project->nama} berhasil disimpan.");
    }

    public function toggleActive(ClientProject $clientProject)
    {
        if (!$this->isAdminSettings()) abort(403);

        $clientProject->update(['is_active' => !$clientProject->is_active]);
        $status = $clientProject->is_active ? 'diaktifkan' : 'dinonaktifkan';
        ActivityLog::record('update', 'Data Project', $clientProject->kode, "Project {$status}");
        return back()->with('success', "Project \"{$clientProject->kode}\" {$status}.");
    }

    public function destroy(ClientProject $clientProject)
    {
        if (!$this->isAdminSettings()) abort(403);

        $kode  = $clientProject->kode;
        $count = $clientProject->employees()->count();
        if ($count > 0) {
            return back()->with('error', "Project \"{$kode}\" tidak bisa dihapus karena masih dipakai oleh {$count} karyawan. Nonaktifkan saja.");
        }
        $clientProject->delete();
        ActivityLog::record('delete', 'Data Project', $kode);
        return back()->with('success', "Project \"{$kode}\" berhasil dihapus.");
    }
}
