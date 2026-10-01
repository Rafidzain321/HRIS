<?php
namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Holiday;
use Illuminate\Http\Request;

class HolidayController extends Controller
{
    public function store(Request $request)
    {
        // Hari libur berlaku untuk semua kantor (Timesheet, Cuti, Absensi Mesin, Gaji) —
        // cuma user yang boleh edit Cuti/Timesheet (sama dengan syarat tombolnya muncul).
        abort_unless(auth()->user()->canAny(['edit-cuti', 'edit-timesheet']), 403, 'Anda tidak memiliki izin mengubah hari libur.');
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
        abort_unless(auth()->user()->canAny(['edit-cuti', 'edit-timesheet']), 403, 'Anda tidak memiliki izin mengubah hari libur.');
        $tanggal = $holiday->tanggal->format('Y-m-d');
        $keterangan = $holiday->keterangan;
        $holiday->delete();
        ActivityLog::record('delete', 'Hari Libur', $tanggal, "Hapus hari libur {$tanggal}: {$keterangan}");
        return back()->with('success', 'Hari libur berhasil dihapus.');
    }
}
