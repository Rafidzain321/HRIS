<?php
namespace App\Http\Controllers;

use App\Models\CcpmManpower;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CcpmController extends Controller
{
    public function index(Request $request)
    {
        // ── PROJECT FILTER ──
        $pid   = $this->activeProjectId();
        $query = CcpmManpower::query()
            ->when($pid, fn($q) => $q->where('project_id', $pid));

        if ($search = $request->get('search')) {
            $query->where(fn($q) => $q
                ->where('name',         'like', "%$search%")
                ->orWhere('badge',      'like', "%$search%")
                ->orWhere('hes_passport','like', "%$search%")
                ->orWhere('job_title',  'like', "%$search%")
            );
        }
        if ($status = $request->get('status')) {
            match ($status) {
                'complete'    => $query->where('status', 'Complete'),
                'in_progress' => $query->where('status', 'like', 'In Progress%'),
                'reject'      => $query->where('status', 'like', 'Reject%'),
                'waiting'     => $query->where('status', 'like', 'Waiting%'),
                default       => null,
            };
        }
        if ($job = $request->get('job')) {
            $query->where('job_title', $job);
        }

        $paginated = $query->orderBy('name')->paginate(50)->withQueryString();
        $paginated->getCollection()->transform(fn($item) => [
            'id'               => $item->id,
            'badge'            => $item->badge,
            'id_card'          => $item->id_card,
            'hes_passport'     => $item->hes_passport,
            'name'             => $item->name,
            'birth_place'      => $item->birth_place,
            'birth_date'       => $item->birth_date?->format('Y-m-d'),
            'ffd_valid_date'   => $item->ffd_valid_date?->format('Y-m-d'),
            'badge_valid_date' => $item->badge_valid_date?->format('Y-m-d'),
            'job_title'        => $item->job_title,
            'team_assignment'  => $item->team_assignment,
            'status'           => $item->status,
            'status_medical'   => $item->status_medical,
        ]);

        $manpower = $paginated->toArray();

        // ── STATS juga difilter per project ──
        $base = CcpmManpower::when($pid, fn($q) => $q->where('project_id', $pid));
        $manpower['complete']    = (clone $base)->where('status', 'Complete')->count();
        $manpower['in_progress'] = (clone $base)->where('status', 'like', 'In Progress%')->count();
        $manpower['reject']      = (clone $base)->where('status', 'like', 'Reject%')->count();
        $manpower['job_titles']  = (clone $base)->distinct()
                                       ->orderBy('job_title')
                                       ->whereNotNull('job_title')
                                       ->pluck('job_title')
                                       ->filter()
                                       ->values();

        return Inertia::render('Ccpm/Index', [
            'manpower' => $manpower,
            'filters'  => $request->only(['search', 'status', 'job']),
        ]);
    }

    public function store(Request $request)
    {
        if ($this->isViewer()) {
            return back()->with('error', 'Viewer tidak memiliki akses untuk mengubah data.');
        }

        $data = $request->validate([
            'badge'            => 'nullable|string|max:30',
            'id_card'          => 'nullable|string|max:20',
            'hes_passport'     => 'nullable|string|max:50',
            'name'             => 'required|string|max:200',
            'birth_place'      => 'nullable|string|max:100',
            'birth_date'       => 'nullable|date',
            'ffd_valid_date'   => 'nullable|date',
            'badge_valid_date' => 'nullable|date',
            'job_title'        => 'nullable|string|max:150',
            'team_assignment'  => 'nullable|string|max:100',
            'status'           => 'nullable|string|max:100',
            'status_medical'   => 'nullable|string|max:100',
        ]);

        // ── Set project_id otomatis ──
        $data['project_id'] = $this->activeProjectId() ?? auth()->user()->project_id;

        $ccpm = CcpmManpower::create($data);
        ActivityLog::record('create', 'CCPM', $ccpm->name, "Tambah manpower CCPM: {$ccpm->name}");
        return redirect()->route('ccpm')->with('success', "Manpower {$data['name']} berhasil ditambahkan.");
    }

    public function update(Request $request, CcpmManpower $ccpm)
    {
        if ($this->isViewer()) {
            return back()->with('error', 'Viewer tidak memiliki akses untuk mengubah data.');
        }

        $data = $request->validate([
            'badge'            => 'nullable|string|max:30',
            'id_card'          => 'nullable|string|max:20',
            'hes_passport'     => 'nullable|string|max:50',
            'name'             => 'required|string|max:200',
            'birth_place'      => 'nullable|string|max:100',
            'birth_date'       => 'nullable|date',
            'ffd_valid_date'   => 'nullable|date',
            'badge_valid_date' => 'nullable|date',
            'job_title'        => 'nullable|string|max:150',
            'team_assignment'  => 'nullable|string|max:100',
            'status'           => 'nullable|string|max:100',
            'status_medical'   => 'nullable|string|max:100',
        ]);

        $ccpm->update($data);
        ActivityLog::record('update', 'CCPM', $ccpm->name, "Edit manpower CCPM: {$ccpm->name}");
        return redirect()->route('ccpm')->with('success', "Data {$ccpm->name} berhasil diperbarui.");
    }

    public function destroy(CcpmManpower $ccpm)
    {
        if ($this->isViewer()) {
            return back()->with('error', 'Viewer tidak memiliki akses untuk mengubah data.');
        }

        $nama = $ccpm->name;
        $ccpm->delete();
        ActivityLog::record('delete', 'CCPM', $nama, "Hapus manpower CCPM: {$nama}");
        return redirect()->route('ccpm')->with('success', "$nama berhasil dihapus.");
    }
}
