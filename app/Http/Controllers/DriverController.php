<?php
namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\DriverDetail;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DriverController extends Controller
{
    private function castStringFields(array $data): array
    {
        foreach (['license_no', 'id_card', 'rfid', 'badge'] as $field) {
            if (isset($data[$field]) && $data[$field] !== null && $data[$field] !== '') {
                $data[$field] = (string) $data[$field];
            }
        }
        return $data;
    }

    public function index(Request $request)
    {
        // ── PROJECT FILTER ──
        $pid   = $this->activeProjectId();
        $query = DriverDetail::query()
            ->when($pid, fn($q) => $q->where('project_id', $pid));

        if ($search = $request->get('search')) {
            $query->where(fn($q) => $q
                ->where('name',       'like', "%$search%")
                ->orWhere('id_card',  'like', "%$search%")
                ->orWhere('license_no','like', "%$search%")
            );
        }
        if ($status = $request->get('status')) {
            match ($status) {
                'approved'    => $query->where('driver_status', 'Approved'),
                'in_progress' => $query->where('driver_status', 'like', 'In Progress%'),
                'waiting'     => $query->where('driver_status', 'like', 'Waiting%'),
                'rejected'    => $query->where('driver_status', 'like', 'Reject%'),
                default       => null,
            };
        }
        if ($license = $request->get('license')) {
            $query->where('license_type', $license);
        }
        if ($permit = $request->get('permit')) {
            if ($permit === 'expired') $query->whereNotNull('permit_expired_date')->where('permit_expired_date', '<', now());
            if ($permit === 'warning') $query->whereNotNull('permit_expired_date')->whereBetween('permit_expired_date', [now(), now()->addDays(30)]);
        }

        $paginated = $query->orderBy('name')->paginate(50)->withQueryString();
        $paginated->getCollection()->transform(fn($d) => [
            'id'                       => $d->id,
            'name'                     => $d->name,
            'id_card'                  => $d->id_card  !== null ? (string) $d->id_card  : null,
            'badge'                    => $d->badge    !== null ? (string) $d->badge    : null,
            'license_type'             => $d->license_type,
            'license_no'               => $d->license_no !== null ? (string) $d->license_no : null,
            'rfid'                     => $d->rfid     !== null ? (string) $d->rfid     : null,
            'posttest_schedule'        => $d->posttest_schedule?->format('Y-m-d'),
            'driver_status'            => $d->driver_status,
            'permit_expired_date'      => $d->permit_expired_date?->format('Y-m-d'),
            'permit_status'            => $d->permit_status,
            'posttest_schedule_status' => $d->posttest_schedule_status,
            'posttest_status'          => $d->posttest_status,
            'date_approve_posttest'    => $d->date_approve_posttest?->format('Y-m-d'),
            'dvp_status'               => $d->dvp_status,
        ]);

        $data = $paginated->toArray();

        // ── STATS juga difilter per project ──
        $base = DriverDetail::when($pid, fn($q) => $q->where('project_id', $pid));
        $data['stats'] = [
            'total'          => (clone $base)->count(),
            'approved'       => (clone $base)->where('driver_status', 'Approved')->count(),
            'in_progress'    => (clone $base)->where('driver_status', 'like', 'In Progress%')->count(),
            'waiting'        => (clone $base)->where('driver_status', 'like', 'Waiting%')->count(),
            'permit_expired' => (clone $base)->whereNotNull('permit_expired_date')->where('permit_expired_date', '<', now())->count(),
            'permit_warning' => (clone $base)->whereNotNull('permit_expired_date')->whereBetween('permit_expired_date', [now(), now()->addDays(30)])->count(),
            'pass'           => (clone $base)->where('posttest_status', 'Pass')->count(),
            'kp_exist'       => (clone $base)->where('dvp_status', 'like', 'KP has been exist%')->count(),
        ];

        return Inertia::render('Driver/Index', [
            'drivers' => $data,
            'filters' => $request->only(['search', 'status', 'license', 'permit']),
        ]);
    }

    public function store(Request $request)
    {
        if ($this->isViewer()) {
            return back()->with('error', 'Viewer tidak memiliki akses untuk mengubah data.');
        }

        $data = $request->validate([
            'name'                     => 'required|string|max:200',
            'id_card'                  => 'nullable|string|max:25',
            'badge'                    => 'nullable|string|max:30',
            'license_type'             => 'nullable|string|max:10',
            'license_no'               => 'nullable|string|max:30',
            'rfid'                     => 'nullable|string|max:20',
            'posttest_schedule'        => 'nullable|date',
            'driver_status'            => 'nullable|string|max:100',
            'permit_expired_date'      => 'nullable|date',
            'posttest_schedule_status' => 'nullable|string|max:50',
            'posttest_status'          => 'nullable|string|max:20',
            'date_approve_posttest'    => 'nullable|date',
            'dvp_status'               => 'nullable|string|max:150',
        ]);

        $data = $this->castStringFields($data);

        // ── Set project_id otomatis ──
        $data['project_id'] = $this->activeProjectId() ?? auth()->user()->project_id;

        $driver = DriverDetail::create($data);
        ActivityLog::record('create', 'Driver', $driver->name, "Tambah driver: {$driver->name}");
        return redirect()->route('driver')->with('success', "Driver {$data['name']} berhasil ditambahkan.");
    }

    public function update(Request $request, DriverDetail $driver)
    {
        if ($this->isViewer()) {
            return back()->with('error', 'Viewer tidak memiliki akses untuk mengubah data.');
        }

        $data = $request->validate([
            'name'                     => 'required|string|max:200',
            'id_card'                  => 'nullable|string|max:25',
            'badge'                    => 'nullable|string|max:30',
            'license_type'             => 'nullable|string|max:10',
            'license_no'               => 'nullable|string|max:30',
            'rfid'                     => 'nullable|string|max:20',
            'posttest_schedule'        => 'nullable|date',
            'driver_status'            => 'nullable|string|max:100',
            'permit_expired_date'      => 'nullable|date',
            'posttest_schedule_status' => 'nullable|string|max:50',
            'posttest_status'          => 'nullable|string|max:20',
            'date_approve_posttest'    => 'nullable|date',
            'dvp_status'               => 'nullable|string|max:150',
        ]);

        $data = $this->castStringFields($data);
        $driver->update($data);
        ActivityLog::record('update', 'Driver', $driver->name, "Update data driver: {$driver->name}");
        return redirect()->route('driver')->with('success', "Data {$driver->name} diperbarui.");
    }

    public function destroy(DriverDetail $driver)
    {
        if ($this->isViewer()) {
            return back()->with('error', 'Viewer tidak memiliki akses untuk mengubah data.');
        }

        $nama = $driver->name;
        $driver->delete();
        ActivityLog::record('delete', 'Driver', $nama, "Hapus driver: {$nama}");
        return redirect()->route('driver')->with('success', "$nama dihapus.");
    }
}