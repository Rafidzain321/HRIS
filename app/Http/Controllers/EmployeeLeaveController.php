<?php
namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\EmployeeLeave;
use App\Models\Holiday;
use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmployeeLeaveController extends Controller
{
    const JATAH_TAHUNAN = 12;

    // Cuti tahunan khusus karyawan Head Office — jatah 12 hari kerja/tahun, reset tiap 1 Januari.
    public function index(Request $request)
    {
        $tahun = (int) $request->get('tahun', now()->year);
        $hoProjectId = Project::where('kode', 'ho')->value('id');

        $employees = Employee::aktif()->where('project_id', $hoProjectId)
            ->with('position')->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'position_id'])
            ->map(fn ($e) => ['id' => $e->id, 'nama_lengkap' => $e->nama_lengkap, 'jabatan' => $e->position?->nama_jabatan ?? '-']);

        $leaves = EmployeeLeave::whereYear('tanggal_mulai', $tahun)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->orderByDesc('tanggal_mulai')
            ->get()
            ->map(fn ($l) => [
                'id'              => $l->id,
                'employee_id'     => $l->employee_id,
                'tanggal_mulai'   => $l->tanggal_mulai->format('Y-m-d'),
                'tanggal_selesai' => $l->tanggal_selesai->format('Y-m-d'),
                'jumlah_hari'     => $l->jumlah_hari,
                'keterangan'      => $l->keterangan,
                'dicatat_oleh'    => $l->dicatat_oleh,
            ]);

        $holidays = Holiday::whereYear('tanggal', $tahun)->get(['id', 'tanggal', 'keterangan', 'tipe'])
            ->map(fn ($h) => ['id' => $h->id, 'tanggal' => $h->tanggal->format('Y-m-d'), 'keterangan' => $h->keterangan, 'tipe' => $h->tipe]);

        return Inertia::render('Cuti/Index', [
            'employees' => $employees,
            'leaves'    => $leaves,
            'holidays'  => $holidays,
            'tahun'     => $tahun,
            'jatah'     => self::JATAH_TAHUNAN,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id'     => 'required|exists:employees,id',
            'tanggal_mulai'   => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'keterangan'      => 'nullable|string|max:200',
        ]);

        $jumlahHari = EmployeeLeave::hitungHariKerja($data['tanggal_mulai'], $data['tanggal_selesai']);
        if ($jumlahHari < 1) {
            return back()->withErrors(['tanggal_selesai' => 'Rentang tanggal ini tidak mengandung hari kerja (semua akhir pekan/hari libur).']);
        }

        $leave = EmployeeLeave::create([
            ...$data,
            'jumlah_hari'  => $jumlahHari,
            'dicatat_oleh' => auth()->user()?->name,
        ]);

        ActivityLog::record('create', 'Cuti', $leave->employee->nama_lengkap ?? '-', "Catat cuti {$jumlahHari} hari kerja ({$data['tanggal_mulai']} s/d {$data['tanggal_selesai']})");

        return back()->with('success', "Cuti {$jumlahHari} hari kerja berhasil dicatat.");
    }

    public function destroy(EmployeeLeave $leave)
    {
        $nama = $leave->employee->nama_lengkap ?? '-';
        $leave->delete();
        ActivityLog::record('delete', 'Cuti', $nama, 'Catatan cuti dihapus');
        return back()->with('success', 'Catatan cuti berhasil dihapus.');
    }
}
