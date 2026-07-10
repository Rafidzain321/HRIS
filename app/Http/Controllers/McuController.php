<?php
namespace App\Http\Controllers;

use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class McuController extends Controller
{
    public function index(Request $request)
    {
        $today = Carbon::today();
        $in30  = $today->copy()->addDays(30);

        $filter    = $request->get('filter', 'all');
        $search    = $request->get('search', '');
        $highlight = $request->get('highlight');

        if ($highlight) $filter = 'all';

        $query = Employee::aktif()->with(['position' => fn($q) => $q->select('id', 'nama_jabatan')]);
        $this->applyProjectFilter($query);

        if ($search) {
            $query->where(fn($q) => $q
                ->where('nama_lengkap', 'like', "%$search%")
                ->orWhere('no_ktp', 'like', "%$search%")
            );
        }

        match ($filter) {
            'expired' => $query->where('exp_mcu', '<', $today),
            'warning' => $query->whereBetween('exp_mcu', [$today, $in30]),
            'ok'      => $query->where('exp_mcu', '>=', $in30),
            'no_data' => $query->whereNull('exp_mcu'),
            default   => null,
        };
        $query->orderBy('exp_mcu');

        $page = $request->get('page', 1);
        if ($highlight) {
            $allIds = (clone $query)->pluck('id')->toArray();
            $pos    = array_search((int) $highlight, $allIds);
            if ($pos !== false) $page = (int) floor($pos / 50) + 1;
        }

        $employees = $query->paginate(50, ['*'], 'page', $page)->appends($request->except('highlight'))
            ->through(fn($e) => [
                'id'                => $e->id,
                'no_ktp'            => $e->no_ktp,
                'id_badge'          => $e->id_badge,
                'nama_lengkap'      => $e->nama_lengkap,
                'jabatan'           => $e->position?->nama_jabatan ?? '-',
                'tgl_mcu'           => $e->tgl_mcu?->format('Y-m-d'),
                'tgl_mcu_fmt'       => $e->tgl_mcu?->format('d M Y'),
                'exp_mcu'           => $e->exp_mcu?->format('Y-m-d'),
                'exp_mcu_fmt'       => $e->exp_mcu?->format('d M Y'),
                'status_mcu'        => $e->status_mcu,
                'lokasi_mcu'        => $e->lokasi_mcu,
                'mcu_status'        => $e->mcu_status,
                'derajat_kesehatan' => $e->derajat_kesehatan,
                'days_left'         => $e->exp_mcu ? $today->diffInDays($e->exp_mcu, false) : null,
            ]);

        $pid  = $this->activeProjectId();
        $base = Employee::aktif()->when($pid, fn($q) => $q->where('project_id', $pid));
        $stats = [
            'total'   => (clone $base)->count(),
            'expired' => (clone $base)->whereNotNull('exp_mcu')->where('exp_mcu', '<', $today)->count(),
            'warning' => (clone $base)->whereNotNull('exp_mcu')->whereBetween('exp_mcu', [$today, $in30])->count(),
            'ok'      => (clone $base)->whereNotNull('exp_mcu')->where('exp_mcu', '>=', $in30)->count(),
            'no_data' => (clone $base)->whereNull('exp_mcu')->count(),
        ];

        return Inertia::render('Compliance/Mcu', compact('employees', 'stats', 'filter', 'search', 'highlight'));
    }

    public function update(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'tgl_mcu'           => 'nullable|date',
            'exp_mcu'           => 'nullable|date',
            'status_mcu'        => 'nullable|string|max:20',
            'lokasi_mcu'        => 'nullable|string|max:100',
            'derajat_kesehatan' => 'nullable|string|max:50',
        ]);
        $employee->update($data);
        return back()->with('success', "MCU {$employee->nama_lengkap} berhasil diperbarui.");
    }

    public function notifications()
    {
        $today = Carbon::today();
        $in30  = $today->copy()->addDays(30);

        $expired = Employee::aktif()->whereNotNull('exp_mcu')->where('exp_mcu', '<', $today)->count();
        $warning = Employee::aktif()->whereNotNull('exp_mcu')->whereBetween('exp_mcu', [$today, $in30])->count();

        $urgent = Employee::aktif()
            ->whereNotNull('exp_mcu')->where('exp_mcu', '<', $in30)
            ->with('position')->orderBy('exp_mcu')->limit(5)->get()
            ->map(fn($e) => [
                'id'        => $e->id,
                'nama'      => $e->nama_lengkap,
                'jabatan'   => $e->position?->nama_jabatan ?? '-',
                'exp_mcu'   => $e->exp_mcu?->format('d M Y'),
                'days_left' => $today->diffInDays($e->exp_mcu, false),
                'status'    => $e->mcu_status,
            ]);

        return response()->json(compact('expired', 'warning', 'urgent'));
    }
}