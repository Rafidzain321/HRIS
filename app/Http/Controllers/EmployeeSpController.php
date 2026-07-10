<?php
namespace App\Http\Controllers;

use App\Models\EmployeeSp;
use App\Models\EmployeeTerminationLog;
use Illuminate\Http\Request;

class EmployeeSpController extends Controller
{
    // ── SP (Surat Peringatan) ──

    public function storeSp(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'tipe_sp'     => 'required|in:SP1,SP2,SP3,SKORSING,PHK',
            'tanggal_sp'  => 'required|date',
            'alasan'      => 'required|string',
            'catatan'     => 'nullable|string',
        ]);

        EmployeeSp::create([
            'employee_id' => $request->employee_id,
            'tipe_sp'     => $request->tipe_sp,
            'tanggal_sp'  => $request->tanggal_sp,
            'alasan'      => $request->alasan,
            'catatan'     => $request->catatan,
            'dibuat_oleh' => auth()->user()->name ?? 'System',
        ]);

        return back()->with('success', "{$request->tipe_sp} berhasil dicatat.");
    }

    public function destroySp(EmployeeSp $sp)
    {
        $sp->delete();
        return back()->with('success', 'Catatan SP dihapus.');
    }

    // Ambil SP + log untuk 1 employee (dipakai di Edit page)
    public function getHistory(int $employeeId)
    {
        $sp = EmployeeSp::where('employee_id', $employeeId)
            ->orderByDesc('tanggal_sp')
            ->get()
            ->map(fn($s) => [
                'id'         => $s->id,
                'tipe_sp'    => $s->tipe_sp,
                'tanggal_sp' => $s->tanggal_sp->format('d M Y'),
                'alasan'     => $s->alasan,
                'catatan'    => $s->catatan,
                'dibuat_oleh'=> $s->dibuat_oleh,
                'created_at' => $s->created_at->format('d M Y H:i'),
            ]);

        $termLog = EmployeeTerminationLog::where('employee_id', $employeeId)
            ->orderByDesc('tanggal_keluar')
            ->get()
            ->map(fn($t) => [
                'id'             => $t->id,
                'tanggal_keluar' => $t->tanggal_keluar->format('d M Y'),
                'alasan_keluar'  => $t->alasan_keluar,
                'catatan_keluar' => $t->catatan_keluar,
                'dicatat_oleh'   => $t->dicatat_oleh,
                'created_at'     => $t->created_at->format('d M Y H:i'),
            ]);

        return response()->json([
            'sp'      => $sp,
            'termLog' => $termLog,
        ]);
    }
}