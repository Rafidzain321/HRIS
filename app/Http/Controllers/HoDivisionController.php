<?php
namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\HoDivision;
use Illuminate\Http\Request;

// Pengaturan > Divisi HO — master divisi Head Office (dipakai Edit Karyawan HO & Pengajuan Training).
class HoDivisionController extends Controller
{
    public function store(Request $request)
    {
        if (!$this->isAdminSettings()) abort(403);

        $data = $request->validate(['nama' => 'required|string|max:100|unique:ho_divisions,nama']);
        $div  = HoDivision::create($data);
        ActivityLog::record('create', 'Divisi HO', $div->nama);
        return back()->with('success', "Divisi \"{$div->nama}\" berhasil ditambahkan.");
    }

    public function update(Request $request, HoDivision $hoDivision)
    {
        if (!$this->isAdminSettings()) abort(403);

        $data = $request->validate(['nama' => "required|string|max:100|unique:ho_divisions,nama,{$hoDivision->id}"]);
        $old  = $hoDivision->nama;
        $hoDivision->update($data);
        ActivityLog::record('update', 'Divisi HO', $data['nama'], "Diubah dari \"{$old}\"");
        return back()->with('success', 'Divisi berhasil diperbarui.');
    }

    public function toggleActive(HoDivision $hoDivision)
    {
        if (!$this->isAdminSettings()) abort(403);

        $hoDivision->update(['is_active' => !$hoDivision->is_active]);
        $status = $hoDivision->is_active ? 'diaktifkan' : 'dinonaktifkan';
        ActivityLog::record('update', 'Divisi HO', $hoDivision->nama, "Divisi {$status}");
        return back()->with('success', "Divisi \"{$hoDivision->nama}\" {$status}.");
    }

    public function destroy(HoDivision $hoDivision)
    {
        if (!$this->isAdminSettings()) abort(403);

        $nama   = $hoDivision->nama;
        $dipakai = $hoDivision->hoDetails()->count() + $hoDivision->trainingRequests()->count();
        if ($dipakai > 0) {
            return back()->with('error', "Divisi \"{$nama}\" tidak bisa dihapus karena masih dipakai karyawan/pengajuan training. Nonaktifkan saja.");
        }
        $hoDivision->delete();
        ActivityLog::record('delete', 'Divisi HO', $nama);
        return back()->with('success', "Divisi \"{$nama}\" berhasil dihapus.");
    }
}
