<?php
namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeLeave;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmployeeAttendanceController extends Controller
{
    // Kehadiran bulanan (rekap, bukan per-hari) khusus karyawan Head Office — HR yang input.
    // "Cuti" tidak diketik manual, selalu dihitung dari EmployeeLeave supaya konsisten dengan
    // halaman Cuti Tahunan.
    public function index(Request $request)
    {
        $tahun = (int) $request->get('tahun', now()->year);
        $bulan = (int) $request->get('bulan', now()->month);
        $hoProjectId = Project::where('kode', 'ho')->value('id');

        $employees = Employee::aktif()->where('project_id', $hoProjectId)
            ->with('position')->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'position_id']);

        $rows = EmployeeAttendance::where('tahun', $tahun)->where('bulan', $bulan)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->get()->keyBy('employee_id');

        $daysInMonth = Carbon::create($tahun, $bulan, 1)->daysInMonth;

        $data = $employees->map(function ($e) use ($rows, $tahun, $bulan) {
            $r = $rows->get($e->id);
            $cuti = EmployeeLeave::hariCutiDalamBulan($e->id, $tahun, $bulan);
            return [
                'employee_id'  => $e->id,
                'nama_lengkap' => $e->nama_lengkap,
                'jabatan'      => $e->position?->nama_jabatan ?? '-',
                'hadir'        => $r->hadir ?? 0,
                'izin'         => $r->izin ?? 0,
                'sakit'        => $r->sakit ?? 0,
                'alpha'        => $r->alpha ?? 0,
                'cuti'         => $cuti,
            ];
        });

        return Inertia::render('Attendance/Index', [
            'employees'    => $data,
            'tahun'        => $tahun,
            'bulan'        => $bulan,
            'days_in_month'=> $daysInMonth,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'tahun'       => 'required|integer|min:2020|max:2100',
            'bulan'       => 'required|integer|min:1|max:12',
            'hadir'       => 'required|integer|min:0|max:31',
            'izin'        => 'required|integer|min:0|max:31',
            'sakit'       => 'required|integer|min:0|max:31',
            'alpha'       => 'required|integer|min:0|max:31',
        ]);

        $daysInMonth = Carbon::create($data['tahun'], $data['bulan'], 1)->daysInMonth;
        $cuti = EmployeeLeave::hariCutiDalamBulan((int) $data['employee_id'], (int) $data['tahun'], (int) $data['bulan']);
        $total = $data['hadir'] + $data['izin'] + $data['sakit'] + $data['alpha'] + $cuti;

        if ($total > $daysInMonth) {
            return back()->withErrors([
                'hadir' => "Total Hadir+Izin+Sakit+Alpha+Cuti ({$total} hari) tidak boleh melebihi jumlah hari di bulan itu ({$daysInMonth} hari). Cuti bulan ini sudah {$cuti} hari (dari Cuti Tahunan).",
            ]);
        }

        EmployeeAttendance::updateOrCreate(
            ['employee_id' => $data['employee_id'], 'tahun' => $data['tahun'], 'bulan' => $data['bulan']],
            [
                'hadir' => $data['hadir'], 'izin' => $data['izin'], 'sakit' => $data['sakit'], 'alpha' => $data['alpha'],
                'dicatat_oleh' => auth()->user()?->name,
            ]
        );

        $employeeNama = Employee::find($data['employee_id'])?->nama_lengkap ?? '-';
        ActivityLog::record('update', 'Kehadiran', $employeeNama, "Rekap kehadiran {$data['bulan']}/{$data['tahun']}: H{$data['hadir']} I{$data['izin']} S{$data['sakit']} A{$data['alpha']}");

        return back()->with('success', 'Kehadiran berhasil disimpan.');
    }

    // Ringkasan persentase kehadiran per semester (Jan-Jun / Jul-Des). Cuti dikeluarkan total
    // dari rumus — hak karyawan, tidak menaikkan atau menurunkan persentase.
    public function semester(Request $request)
    {
        $tahun    = (int) $request->get('tahun', now()->year);
        $semester = (int) $request->get('semester', now()->month <= 6 ? 1 : 2);
        $bulanAwal = $semester === 1 ? 1 : 7;
        $bulanAkhir = $semester === 1 ? 6 : 12;

        $hoProjectId = Project::where('kode', 'ho')->value('id');
        $employees = Employee::aktif()->where('project_id', $hoProjectId)
            ->with('position')->orderBy('nama_lengkap')->get(['id', 'nama_lengkap', 'position_id']);

        $rows = EmployeeAttendance::where('tahun', $tahun)
            ->whereBetween('bulan', [$bulanAwal, $bulanAkhir])
            ->whereIn('employee_id', $employees->pluck('id'))
            ->get()->groupBy('employee_id');

        $result = $employees->map(function ($e) use ($rows, $tahun, $bulanAwal, $bulanAkhir) {
            $recs = $rows->get($e->id, collect());
            $hadir = $recs->sum('hadir');
            $izin  = $recs->sum('izin');
            $sakit = $recs->sum('sakit');
            $alpha = $recs->sum('alpha');
            $dasar = $hadir + $izin + $sakit + $alpha;
            $pct = $dasar > 0 ? round($hadir / $dasar * 100, 1) : null;

            $cuti = 0;
            for ($b = $bulanAwal; $b <= $bulanAkhir; $b++) {
                $cuti += EmployeeLeave::hariCutiDalamBulan($e->id, $tahun, $b);
            }

            return [
                'employee_id'  => $e->id,
                'nama_lengkap' => $e->nama_lengkap,
                'jabatan'      => $e->position?->nama_jabatan ?? '-',
                'hadir' => $hadir, 'izin' => $izin, 'sakit' => $sakit, 'alpha' => $alpha, 'cuti' => $cuti,
                'persentase' => $pct,
                'bulan_terisi' => $recs->count(),
            ];
        });

        return response()->json(['rows' => $result]);
    }
}
