<?php
namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\HoDivision;
use Illuminate\Http\Request;

// Pengaturan > Departemen — master departemen Head Office (tabel ho_divisions) (dipakai Edit Karyawan HO & Pengajuan Training).
class HoDivisionController extends Controller
{
    public function store(Request $request)
    {
        if (!$this->isAdminSettings()) abort(403);

        $data = $request->validate(['nama' => 'required|string|max:100|unique:ho_divisions,nama']);
        $div  = HoDivision::create($data);
        ActivityLog::record('create', 'Departemen', $div->nama);
        return back()->with('success', "Departemen \"{$div->nama}\" berhasil ditambahkan.");
    }

    public function update(Request $request, HoDivision $hoDivision)
    {
        if (!$this->isAdminSettings()) abort(403);

        $data = $request->validate(['nama' => "required|string|max:100|unique:ho_divisions,nama,{$hoDivision->id}"]);
        $old  = $hoDivision->nama;
        $hoDivision->update($data);
        ActivityLog::record('update', 'Departemen', $data['nama'], "Diubah dari \"{$old}\"");
        return back()->with('success', 'Departemen berhasil diperbarui.');
    }

    public function toggleActive(HoDivision $hoDivision)
    {
        if (!$this->isAdminSettings()) abort(403);

        $hoDivision->update(['is_active' => !$hoDivision->is_active]);
        $status = $hoDivision->is_active ? 'diaktifkan' : 'dinonaktifkan';
        ActivityLog::record('update', 'Departemen', $hoDivision->nama, "Departemen {$status}");
        return back()->with('success', "Departemen \"{$hoDivision->nama}\" {$status}.");
    }

    public function destroy(HoDivision $hoDivision)
    {
        if (!$this->isAdminSettings()) abort(403);

        $nama   = $hoDivision->nama;
        $dipakai = $hoDivision->hoDetails()->count() + $hoDivision->trainingRequests()->count();
        if ($dipakai > 0) {
            return back()->with('error', "Departemen \"{$nama}\" tidak bisa dihapus karena masih dipakai karyawan/pengajuan training. Nonaktifkan saja.");
        }
        $hoDivision->delete();
        ActivityLog::record('delete', 'Departemen', $nama);
        return back()->with('success', "Departemen \"{$nama}\" berhasil dihapus.");
    }
}
