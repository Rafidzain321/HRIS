<?php
namespace App\Http\Controllers;

use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BadgeKpController extends Controller
{
    public function index(Request $request)
    {
        $today = Carbon::today();
        $in30  = $today->copy()->addDays(30);

        $filter    = $request->get('filter', 'all');
        $search    = $request->get('search', '');
        $tab       = $request->get('tab', 'badge');
        $highlight = $request->get('highlight');

        if ($highlight) $filter = 'all';

        $query = Employee::aktif()->with('position');
        $this->applyProjectFilter($query);

        if ($search) {
            $query->where(fn($q) => $q
                ->where('nama_lengkap', 'like', "%$search%")
                ->orWhere('no_ktp', 'like', "%$search%")
            );
        }

        if ($tab === 'badge') {
            match ($filter) {
                'expired' => $query->where('expire_badge', '<', $today),
                'warning' => $query->whereBetween('expire_badge', [$today, $in30]),
                'ok'      => $query->where('expire_badge', '>=', $in30),
                default   => null,
            };
            $query->orderBy('expire_badge');
        } else {
            match ($filter) {
                'expired' => $query->where('exp_kp', '<', $today),
                'warning' => $query->whereBetween('exp_kp', [$today, $in30]),
                'ok'      => $query->where('exp_kp', '>=', $in30),
                default   => null,
            };
            $query->orderBy('exp_kp');
        }

        $page = $request->get('page', 1);
        if ($highlight) {
            $allIds = (clone $query)->pluck('id')->toArray();
            $pos    = array_search((int) $highlight, $allIds);
            if ($pos !== false) $page = (int) floor($pos / 50) + 1;
        }

        $employees = $query->paginate(50, ['*'], 'page', $page)->appends($request->except('highlight'))
            ->through(fn($e) => [
                'id'               => $e->id,
                'no_ktp'           => $e->no_ktp,
                'id_badge'         => $e->id_badge,
                'nama_lengkap'     => $e->nama_lengkap,
                'jabatan'          => $e->position?->nama_jabatan ?? '-',
                'expire_badge'     => $e->expire_badge?->format('Y-m-d'),
                'expire_badge_fmt' => $e->expire_badge?->format('d M Y'),
                'badge_status'     => $e->badge_status,
                'badge_days'       => $e->expire_badge ? $today->diffInDays($e->expire_badge, false) : null,
                'status_kp'        => $e->status_kp,
                'exp_kp'           => $e->exp_kp?->format('Y-m-d'),
                'exp_kp_fmt'       => $e->exp_kp?->format('d M Y'),
                'kp_days'          => $e->exp_kp ? $today->diffInDays($e->exp_kp, false) : null,
                'rfid'             => $e->rfid,
            ]);

        $pid  = $this->activeProjectId();
        $base = Employee::aktif()->when($pid, fn($q) => $q->where('project_id', $pid));
        $stats = [
            'badge_total'   => (clone $base)->count(),
            'badge_expired' => (clone $base)->whereNotNull('expire_badge')->where('expire_badge', '<', $today)->count(),
            'badge_warning' => (clone $base)->whereNotNull('expire_badge')->whereBetween('expire_badge', [$today, $in30])->count(),
            'badge_ok'      => (clone $base)->whereNotNull('expire_badge')->where('expire_badge', '>=', $in30)->count(),
            'badge_nodata'  => (clone $base)->whereNull('expire_badge')->count(),
            'kp_ada'        => (clone $base)->where('status_kp', 'KP has been exist')->count(),
            'kp_expired'    => (clone $base)->whereNotNull('exp_kp')->where('exp_kp', '<', $today)->count(),
            'kp_warning'    => (clone $base)->whereNotNull('exp_kp')->whereBetween('exp_kp', [$today, $in30])->count(),
            'kp_ok'         => (clone $base)->whereNotNull('exp_kp')->where('exp_kp', '>=', $in30)->count(),
            'kp_nodata'     => (clone $base)->whereNull('exp_kp')->count(),
        ];

        return Inertia::render('Compliance/Badge', compact('employees', 'stats', 'filter', 'search', 'tab', 'highlight'));
    }

    public function update(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'expire_badge' => 'nullable|date',
            'status_kp'    => 'nullable|string|max:100',
            'exp_kp'       => 'nullable|date',
            'rfid'         => 'nullable|string|max:50',
        ]);
        $employee->update($data);
        return back()->with('success', "Badge/KP {$employee->nama_lengkap} berhasil diperbarui.");
    }
}