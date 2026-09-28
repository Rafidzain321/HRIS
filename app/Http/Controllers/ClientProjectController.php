<?php
namespace App\Http\Controllers;

use App\Models\ClientProject;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

// "Data Project" — project riil (kode kontrak/pekerjaan, mis. "AKM-PP"), beda dari Project
// (kantor/entitas payroll). Dikelola HR/super-admin lewat Pengaturan, dipakai buat penanda
// karyawan mana pegang project mana (many-to-many, lihat Employee::clientProjects()).
class ClientProjectController extends Controller
{
    public function store(Request $request)
    {
        if (!$this->isAdminSettings()) abort(403);

        $data = $request->validate([
            'kode' => 'required|string|max:50|unique:client_projects,kode',
        ]);
        $cp = ClientProject::create($data);
        ActivityLog::record('create', 'Data Project', $cp->kode);
        return back()->with('success', "Project \"{$cp->kode}\" berhasil ditambahkan.");
    }

    public function update(Request $request, ClientProject $clientProject)
    {
        if (!$this->isAdminSettings()) abort(403);

        $data = $request->validate([
            'kode' => "required|string|max:50|unique:client_projects,kode,{$clientProject->id}",
        ]);
        $old = $clientProject->kode;
        $clientProject->update($data);
        ActivityLog::record('update', 'Data Project', $data['kode'], "Diubah dari \"{$old}\"");
        return back()->with('success', 'Project berhasil diperbarui.');
    }

    public function destroy(ClientProject $clientProject)
    {
        if (!$this->isAdminSettings()) abort(403);

        $kode  = $clientProject->kode;
        $count = $clientProject->employees()->count();
        if ($count > 0) {
            return back()->with('error', "Project \"{$kode}\" tidak bisa dihapus karena masih dipakai oleh {$count} karyawan.");
        }
        $clientProject->delete();
        ActivityLog::record('delete', 'Data Project', $kode);
        return back()->with('success', "Project \"{$kode}\" berhasil dihapus.");
    }
}
