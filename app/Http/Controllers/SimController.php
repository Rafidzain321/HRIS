<?php
namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SimController extends Controller
{
    public function index(Request $request)
    {
        $today = Carbon::today();
        $in30  = $today->copy()->addDays(30);

        $filter    = $request->get('filter', 'all');
        $search    = $request->get('search', '');
        $highlight = $request->get('highlight');

        if ($highlight) $filter = 'all';

        $query = Employee::aktif()->with('position')->whereNotNull('type_sim');
        $this->applyProjectFilter($query);

        if ($search) {
            $query->where(fn($q) => $q
                ->where('nama_lengkap', 'like', "%$search%")
                ->orWhere('no_ktp', 'like', "%$search%")
                ->orWhere('no_sim', 'like', "%$search%")
            );
        }

        match ($filter) {
            'expired' => $query->where('expired_sim', '<', $today),
            'warning' => $query->whereBetween('expired_sim', [$today, $in30]),
            'ok'      => $query->where('expired_sim', '>=', $in30),
            default   => null,
        };
        $query->orderBy('expired_sim');

        $page = $request->get('page', 1);
        if ($highlight) {
            $allIds = (clone $query)->pluck('id')->toArray();
            $pos    = array_search((int) $highlight, $allIds);
            if ($pos !== false) $page = (int) floor($pos / 50) + 1;
        }

        $employees = $query->paginate(50, ['*'], 'page', $page)->appends($request->except('highlight'))
            ->through(fn($e) => [
                'id'              => $e->id,
                'no_ktp'          => $e->no_ktp,
                'id_badge'        => $e->id_badge,
                'nama_lengkap'    => $e->nama_lengkap,
                'jabatan'         => $e->position?->nama_jabatan ?? '-',
                'type_sim'        => $e->type_sim,
                'no_sim'          => $e->no_sim,
                'rfid'            => $e->rfid,
                'expired_sim'     => $e->expired_sim?->format('Y-m-d'),
                'expired_sim_fmt' => $e->expired_sim?->format('d M Y'),
                'sim_kota_keluar' => $e->sim_kota_keluar,
                'sim_status'      => $e->sim_status,
                'days_left'       => $e->expired_sim ? $today->diffInDays($e->expired_sim, false) : null,
            ]);

        $pid = $this->activeProjectId();
        $all = Employee::aktif()->whereNotNull('type_sim')
                   ->when($pid, fn($q) => $q->where('project_id', $pid));
        $stats = [
            'total'   => (clone $all)->count(),
            'expired' => (clone $all)->where('expired_sim', '<', $today)->count(),
            'warning' => (clone $all)->whereBetween('expired_sim', [$today, $in30])->count(),
            'ok'      => (clone $all)->where('expired_sim', '>=', $in30)->count(),
            'no_data' => (clone $all)->whereNull('expired_sim')->count(),
        ];

        return Inertia::render('Compliance/Sim', compact('employees', 'stats', 'filter', 'search', 'highlight'));
    }

    public function update(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'type_sim'        => 'nullable|string|max:10',
            'no_sim'          => 'nullable|string|max:30',
            'sim_kota_keluar' => 'nullable|string|max:100',
            'expired_sim'     => 'nullable|date',
            'rfid'            => 'nullable|string|max:50',
        ]);
        $employee->update($data);
        ActivityLog::record('update', 'SIM', $employee->nama_lengkap, "Update SIM: {$employee->nama_lengkap}");
        return back()->with('success', "SIM {$employee->nama_lengkap} berhasil diperbarui.");
    }
}