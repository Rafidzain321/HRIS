<?php
namespace App\Http\Controllers;

use App\Models\ClientProject;
use App\Models\ActivityLog;
use App\Models\Project;
use Illuminate\Http\Request;

// "Data Project" — project riil (kode kontrak/pekerjaan, mis. "AKM-PP"), beda dari Project
// (kantor/entitas payroll). Tiap project dihubungkan ke satu atau beberapa kantor, bisa diaktif/nonaktifkan.
// Dipakai buat penanda karyawan mana pegang project mana (many-to-many, lihat Employee::clientProjects()).
class ClientProjectController extends Controller
{
    const KANTOR_RULES = [
        'project_ids'   => 'required|array|min:1',
        'project_ids.*' => 'exists:projects,id',
    ];
    const KANTOR_MESSAGES = ['project_ids.required' => 'Kantor wajib dipilih.', 'project_ids.min' => 'Kantor wajib dipilih.'];

    public function store(Request $request)
    {
        if (!$this->isAdminSettings()) abort(403);

        $data = $request->validate(['kode' => 'required|string|max:50|unique:client_projects,kode'] + self::KANTOR_RULES, self::KANTOR_MESSAGES);
        $cp = ClientProject::create(['kode' => $data['kode']]);
        $cp->kantors()->sync($data['project_ids']);
        ActivityLog::record('create', 'Data Project', $cp->kode, 'Kantor: ' . $this->namaKantor($cp));
        return back()->with('success', "Project \"{$cp->kode}\" berhasil ditambahkan.");
    }

    public function update(Request $request, ClientProject $clientProject)
    {
        if (!$this->isAdminSettings()) abort(403);

        $data = $request->validate(['kode' => "required|string|max:50|unique:client_projects,kode,{$clientProject->id}"] + self::KANTOR_RULES, self::KANTOR_MESSAGES);
        $old       = $clientProject->kode;
        $oldKantor = $this->namaKantor($clientProject);
        $clientProject->update(['kode' => $data['kode']]);
        $clientProject->kantors()->sync($data['project_ids']);
        $newKantor = $this->namaKantor($clientProject->load('kantors'));
        ActivityLog::record('update', 'Data Project', $data['kode'], "Kode: \"{$old}\" -> \"{$data['kode']}\", Kantor: {$oldKantor} -> {$newKantor}");
        return back()->with('success', 'Project berhasil diperbarui.');
    }

    // "Edit Kantor → Pilih Project": set sekaligus project apa saja yang terhubung ke satu kantor.
    // Yang dicentang ditambahkan ke kantor ini (hubungan ke kantor lain tetap); yang tidak dicentang dilepas dari kantor ini saja.
    public function syncKantor(Request $request, Project $project)
    {
        if (!$this->isAdminSettings()) abort(403);

        $data = $request->validate([
            'client_project_ids'   => 'nullable|array',
            'client_project_ids.*' => 'exists:client_projects,id',
        ]);
        $ids = array_map('intval', $data['client_project_ids'] ?? []);

        $lepas = ClientProject::whereIn('id', $project->clientProjects()->sync($ids)['detached'])->pluck('kode');

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

    private function namaKantor(ClientProject $cp): string
    {
        return $cp->kantors->pluck('nama')->sort()->implode(', ') ?: '-';
    }
}
