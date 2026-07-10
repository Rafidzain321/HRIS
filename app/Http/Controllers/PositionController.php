<?php
namespace App\Http\Controllers;

use App\Models\Position;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class PositionController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'nama_jabatan' => 'required|string|max:150|unique:positions,nama_jabatan',
        ]);
        $pos = Position::create($data);
        ActivityLog::record('create', 'Jabatan', $pos->nama_jabatan);
        return back()->with('success', "Jabatan \"{$pos->nama_jabatan}\" berhasil ditambahkan.");
    }

    public function update(Request $request, Position $position)
    {
        $data = $request->validate([
            'nama_jabatan' => "required|string|max:150|unique:positions,nama_jabatan,{$position->id}",
        ]);
        $old = $position->nama_jabatan;
        $position->update($data);
        ActivityLog::record('update', 'Jabatan', $data['nama_jabatan'], "Diubah dari \"{$old}\"");
        return back()->with('success', "Jabatan berhasil diperbarui.");
    }

    public function destroy(Position $position)
    {
        $nama  = $position->nama_jabatan;
        $count = $position->employees()->count();
        if ($count > 0) {
            return back()->with('error', "Jabatan \"{$nama}\" tidak bisa dihapus karena masih dipakai oleh {$count} karyawan.");
        }
        $position->delete();
        ActivityLog::record('delete', 'Jabatan', $nama);
        return back()->with('success', "Jabatan \"{$nama}\" berhasil dihapus.");
    }
}
