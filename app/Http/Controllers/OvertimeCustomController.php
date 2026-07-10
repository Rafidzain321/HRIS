<?php
namespace App\Http\Controllers;

use App\Models\Holiday;
use App\Models\OvertimeCustom;
use App\Models\Timesheet;
use App\Models\TimesheetMember;
use Carbon\Carbon;
use Illuminate\Http\Request;

class OvertimeCustomController extends Controller
{
    // GET /overtime-custom?tahun=&bulan=
    public function index(Request $request)
    {
        $pid   = $this->activeProjectId();
        $tahun = (int) $request->get('tahun', now()->year);
        $bulan = (int) $request->get('bulan', now()->month);

        $members = TimesheetMember::where('aktif', true)
            ->where('project_id', $pid)
            ->with('employee.position')
            ->orderBy('urutan')
            ->get();

        $saved = OvertimeCustom::where('project_id', $pid)
            ->where('tahun', $tahun)
            ->where('bulan', $bulan)
            ->get()
            ->groupBy('employee_id');

        // Hitung hari Sabtu & Minggu/libur dari timesheet
        $daysInMonth = Carbon::create($tahun, $bulan)->daysInMonth;
        $holidays = Holiday::inMonth($tahun, $bulan)
            ->get()->keyBy(fn($h) => (int) $h->tanggal->format('j'));

        $employeeIds = $members->pluck('employee.id')->filter()->toArray();
        $timesheets = Timesheet::where('tahun', $tahun)
            ->where('bulan', $bulan)
            ->whereIn('employee_id', $employeeIds)
            ->get()
            ->groupBy('employee_id');

        $rows = $members->map(function ($m) use ($saved, $timesheets, $daysInMonth, $holidays, $tahun, $bulan) {
            $emp  = $m->employee;
            if (!$emp) return null;

            $ots    = $saved->get($emp->id, collect());
            $sabtu  = $ots->firstWhere('kategori', 'sabtu');
            $minggu = $ots->firstWhere('kategori', 'minggu');

            // Hitung hari Sabtu & Minggu/libur dari timesheet
            $empTs = $timesheets->get($emp->id, collect())->keyBy('hari');
            $hSabtu = 0; $hMinggu = 0;
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $val = $empTs->get($d)?->nilai;
                if ($val === null || $val === '' || !is_numeric($val)) continue;
                $date   = Carbon::create($tahun, $bulan, $d);
                $isSun  = $date->isSunday();
                $isHol  = $holidays->has($d) && !$isSun;
                $isSat  = $date->isSaturday();
                if ($isSat && !$isHol)  $hSabtu++;
                if ($isSun || $isHol)   $hMinggu++;
            }

            return [
                'employee_id'  => $emp->id,
                'id_badge'     => $m->id_badge,
                'nama_lengkap' => $m->nama_override ?? $emp->nama_lengkap ?? '—',
                'jabatan'      => $emp->position?->nama_jabatan ?? '—',
                'sub_group'        => $m->sub_group,
                'sabtu_tarif'  => $sabtu?->tarif_per_hari ?? 0,
                'minggu_tarif' => $minggu?->tarif_per_hari ?? 0,
                'sabtu_hari'  => $sabtu?->jumlah_hari > 0 ? $sabtu->jumlah_hari : $hSabtu,
                'minggu_hari' => $minggu?->jumlah_hari > 0 ? $minggu->jumlah_hari : $hMinggu,
                'sabtu_hari_auto'  => $hSabtu,
                'minggu_hari_auto' => $hMinggu,
            ];
        })->filter()->values();

        return response()->json(['rows' => $rows]);
    }

    // PUT /overtime-custom/{employeeId}
    public function update(Request $request, int $employeeId)
    {
        if ($this->isViewer()) {
            return response()->json(['ok' => false], 403);
        }

        $pid = $this->activeProjectId();

        $request->validate([
            'tahun'          => 'required|integer',
            'bulan'          => 'required|integer',
            'kategori'       => 'required|in:sabtu,minggu',
            'tarif_per_hari' => 'required|integer|min:0',
            'jumlah_hari'    => 'nullable|integer|min:0',
        ]);

        OvertimeCustom::updateOrCreate(
            [
                'employee_id' => $employeeId,
                'project_id'  => $pid,
                'tahun'       => $request->tahun,
                'bulan'       => $request->bulan,
                'kategori'    => $request->kategori,
            ],
            [
                'tarif_per_hari' => $request->tarif_per_hari,
                'jumlah_hari'    => $request->jumlah_hari ?? 0,
            ]
        );

        return response()->json(['ok' => true]);
    }
}