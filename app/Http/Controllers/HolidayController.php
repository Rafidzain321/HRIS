<?php
namespace App\Http\Controllers;

use App\Models\Holiday;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HolidayController extends Controller
{
    public function index()
    {
        // Pengaturan sekarang hanya placeholder — hari libur dikelola dari tab di Timesheet
        return Inertia::render('Pengaturan/Index');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'tanggal'    => 'required|date|unique:holidays,tanggal',
            'keterangan' => 'required|string|max:200',
            'tipe'       => 'required|in:libur_nasional,cuti_bersama,libur_khusus',
        ]);
        Holiday::create($data);
        return back()->with('success', "Hari libur {$data['tanggal']} berhasil ditambahkan.");
    }

    public function destroy(Holiday $holiday)
    {
        $holiday->delete();
        return back()->with('success', 'Hari libur berhasil dihapus.');
    }
}
