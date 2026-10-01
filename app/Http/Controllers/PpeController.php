<?php
namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\Ppe;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PpeController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search', '');
        $filter = $request->get('filter', 'all');

        $query = Employee::with(['position', 'ppe']);
        $this->applyProjectFilter($query);

        if ($search) {
            $query->where(fn($q) => $q
                ->where('nama_lengkap', 'like', "%$search%")
                ->orWhere('id_badge', 'like', "%$search%")
            );
        }

        if ($filter === 'has_frc') {
            $query->whereHas('ppe', fn($q) => $q->whereNotNull('frc'));
        } elseif ($filter === 'no_frc') {
            $query->where(fn($q) => $q
                ->whereDoesntHave('ppe')
                ->orWhereHas('ppe', fn($q2) => $q2->whereNull('frc'))
            );
        } elseif (in_array($filter, ['S', 'M', 'L', 'XL', 'XXL', 'XXXL', 'XXXXL'])) {
            $query->whereHas('ppe', fn($q) => $q->where('frc', $filter));
        }

        $employees = $query->orderBy('nama_lengkap')->paginate(50)->withQueryString()
            ->through(fn($e) => $this->mapPpeRow($e));

        $pid    = $this->activeProjectId();
        $base   = Employee::aktif()->when($pid, fn($q) => $q->where('project_id', $pid));
        $ppeQ   = fn() => Ppe::when($pid, fn($q) => $q->whereHas('employee', fn($eq) => $eq->where('project_id', $pid)));
        $allPpe = $ppeQ()->selectRaw('frc, count(*) as total')->groupBy('frc')->get()->keyBy('frc');

        $hasFrc = $ppeQ()->whereNotNull('frc')->count();
        $stats = [
            'total'    => (clone $base)->count(),
            'has_frc'  => $hasFrc,
            'no_frc'   => (clone $base)->count() - $hasFrc,
            'frc_L'    => $allPpe->get('L')?->total    ?? 0,
            'frc_M'    => $allPpe->get('M')?->total    ?? 0,
            'frc_XL'   => $allPpe->get('XL')?->total   ?? 0,
            'frc_XXL'  => $allPpe->get('XXL')?->total  ?? 0,
            'frc_XXXL' => $allPpe->get('XXXL')?->total ?? 0,
        ];

        return Inertia::render('Compliance/Ppe', compact('employees', 'stats', 'search', 'filter'));
    }

    public function update(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'frc'             => 'nullable|string|max:10',
            'safety_shoes'    => 'nullable|string|max:10',
            'safety_glass'    => 'nullable|boolean',
            'safety_vest'     => 'nullable|boolean',
            'ear_plug'        => 'nullable|boolean',
            'white_helmet'    => 'nullable|boolean',
            'helmet'          => 'nullable|boolean',
            'tgl_frc'         => 'nullable|date',
            'tgl_frc_2'       => 'nullable|date',
            'tgl_frc_3'       => 'nullable|date',
            'tgl_frc_4'       => 'nullable|date',
            'tgl_sepatu'      => 'nullable|date',
            'tgl_sepatu_2'    => 'nullable|date',
            'tgl_sepatu_3'    => 'nullable|date',
            'tgl_helm'        => 'nullable|date',
            'tgl_helm_orange' => 'nullable|date',
            'tgl_glass'       => 'nullable|date',
            'tgl_glass_2'     => 'nullable|date',
            'tgl_vest'        => 'nullable|date',
            'tgl_ear_plug'    => 'nullable|date',
            'tgl_ear_plug_2'  => 'nullable|date',
            'catatan'         => 'nullable|string|max:500',
        ]);
        Ppe::updateOrCreate(['employee_id' => $employee->id], $data);
        ActivityLog::record('update', 'PPE', $employee->nama_lengkap, "Update PPE: {$employee->nama_lengkap}");
        return back()->with('success', "PPE {$employee->nama_lengkap} berhasil diperbarui.");
    }

    const TANGGAL = [
        'tgl_frc', 'tgl_frc_2', 'tgl_frc_3', 'tgl_frc_4', 'tgl_sepatu', 'tgl_sepatu_2', 'tgl_sepatu_3',
        'tgl_helm', 'tgl_helm_orange', 'tgl_glass', 'tgl_glass_2', 'tgl_vest', 'tgl_ear_plug', 'tgl_ear_plug_2',
    ];

    private function mapPpeRow(Employee $e): array
    {
        $p = $e->ppe;
        $row = [
            'id'           => $e->id,
            'id_badge'     => $e->id_badge,
            'no_ktp'       => $e->no_ktp,
            'nama_lengkap' => $e->nama_lengkap,
            'jabatan'      => $e->position?->nama_jabatan ?? '-',
            'status'       => $e->status,
            'ppe_id'       => $p?->id,
            'frc'          => $p?->frc,
            'safety_shoes' => $p?->safety_shoes,
            'safety_glass' => $p?->safety_glass ?? false,
            'safety_vest'  => $p?->safety_vest ?? false,
            'ear_plug'     => $p?->ear_plug ?? false,
            'catatan'      => $p?->catatan,
            'white_helmet' => (bool) ($p?->white_helmet ?? false),
            'helmet'       => (bool) ($p?->helmet ?? false),
        ];
        // Tiap tanggal dikirim 2 versi: untuk input (Y-m-d) & untuk ditampilkan (d M Y).
        foreach (self::TANGGAL as $field) {
            $row[$field]          = $p?->$field?->format('Y-m-d');
            $row["{$field}_fmt"] = $p?->$field?->format('d M Y');
        }
        return $row;
    }
}