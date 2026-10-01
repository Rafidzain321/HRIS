<?php
namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BadgeKpController extends Controller
{
    public function index(Request $request)
    {
        $today     = Carbon::today();
        $search    = $request->get('search', '');
        $tab       = $request->get('tab', 'badge');
        $highlight = $request->get('highlight');
        $filter    = $highlight ? 'all' : $request->get('filter', 'all');

        $query = Employee::aktif()->with('position');
        $this->applyProjectFilter($query);

        if ($search) {
            $query->where(fn($q) => $q
                ->where('nama_lengkap', 'like', "%$search%")
                ->orWhere('no_ktp', 'like', "%$search%")
            );
        }

        $col = $tab === 'badge' ? 'expire_badge' : 'exp_kp';
        $this->filterExpiry($query, $col, $filter);
        $query->orderBy($col);
        $page = $this->highlightPage($query, $highlight, $request);

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
        $badge = $this->expiryCounts($base, 'expire_badge');
        $kp    = $this->expiryCounts($base, 'exp_kp');
        $stats = [
            'badge_total'   => (clone $base)->count(),
            'badge_expired' => $badge['expired'],
            'badge_warning' => $badge['warning'],
            'badge_ok'      => $badge['ok'],
            'badge_nodata'  => $badge['no_data'],
            'kp_ada'        => (clone $base)->where('status_kp', 'KP has been exist')->count(),
            'kp_expired'    => $kp['expired'],
            'kp_warning'    => $kp['warning'],
            'kp_ok'         => $kp['ok'],
            'kp_nodata'     => $kp['no_data'],
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
        ActivityLog::record('update', 'Badge/KP', $employee->nama_lengkap, "Update Badge/KP: {$employee->nama_lengkap}");
        return back()->with('success', "Badge/KP {$employee->nama_lengkap} berhasil diperbarui.");
    }
}