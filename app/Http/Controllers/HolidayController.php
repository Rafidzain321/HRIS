<?php
namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Holiday;
use Illuminate\Http\Request;

class HolidayController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'tanggal'    => 'required|date|unique:holidays,tanggal',
            'keterangan' => 'required|string|max:200',
            'tipe'       => 'required|in:libur_nasional,cuti_bersama,libur_khusus',
        ]);
        Holiday::create($data);
        ActivityLog::record('create', 'Hari Libur', $data['tanggal'], "Tambah hari libur {$data['tanggal']}: {$data['keterangan']}");
        return back()->with('success', "Hari libur {$data['tanggal']} berhasil ditambahkan.");
    }

    public function destroy(Holiday $holiday)
    {
        $tanggal = $holiday->tanggal->format('Y-m-d');
        $keterangan = $holiday->keterangan;
        $holiday->delete();
        ActivityLog::record('delete', 'Hari Libur', $tanggal, "Hapus hari libur {$tanggal}: {$keterangan}");
        return back()->with('success', 'Hari libur berhasil dihapus.');
    }
}
