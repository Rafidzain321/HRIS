<?php
namespace App\Http\Controllers;

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
            'tgl_helm'      => 'nullable|date',
            'tgl_helm_orange' => 'nullable|date',
            'tgl_glass'       => 'nullable|date',
            'tgl_glass_2'     => 'nullable|date',
            'tgl_vest'        => 'nullable|date',
            'tgl_ear_plug'    => 'nullable|date',
            'tgl_ear_plug_2'  => 'nullable|date',
            'catatan'         => 'nullable|string|max:500',
        ]);
        Ppe::updateOrCreate(['employee_id' => $employee->id], $data);
        return back()->with('success', "PPE {$employee->nama_lengkap} berhasil diperbarui.");
    }

    /**
     * Bentuk 1 baris data PPE karyawan untuk response index().
     */
    private function mapPpeRow(Employee $e): array
    {
        return [
            'id'              => $e->id,
            'id_badge'        => $e->id_badge,
            'no_ktp'          => $e->no_ktp,
            'nama_lengkap'    => $e->nama_lengkap,
            'jabatan'         => $e->position?->nama_jabatan ?? '-',
            'status'          => $e->status,
            'ppe_id'          => $e->ppe?->id,
            'frc'             => $e->ppe?->frc,
            'safety_shoes'    => $e->ppe?->safety_shoes,
            'safety_glass'    => $e->ppe?->safety_glass ?? false,
            'safety_vest'     => $e->ppe?->safety_vest  ?? false,
            'ear_plug'        => $e->ppe?->ear_plug      ?? false,
            'catatan'         => $e->ppe?->catatan,
            'tgl_frc'         => $e->ppe?->tgl_frc?->format('Y-m-d'),
            'tgl_frc_fmt'     => $e->ppe?->tgl_frc?->format('d M Y'),
            'tgl_frc_2'       => $e->ppe?->tgl_frc_2?->format('Y-m-d'),
            'tgl_frc_2_fmt'   => $e->ppe?->tgl_frc_2?->format('d M Y'),
            'tgl_frc_3'       => $e->ppe?->tgl_frc_3?->format('Y-m-d'),
            'tgl_frc_3_fmt'   => $e->ppe?->tgl_frc_3?->format('d M Y'),
            'tgl_frc_4'       => $e->ppe?->tgl_frc_4?->format('Y-m-d'),
            'tgl_frc_4_fmt'   => $e->ppe?->tgl_frc_4?->format('d M Y'),
            'tgl_sepatu'      => $e->ppe?->tgl_sepatu?->format('Y-m-d'),
            'tgl_sepatu_fmt'  => $e->ppe?->tgl_sepatu?->format('d M Y'),
            'tgl_sepatu_2'    => $e->ppe?->tgl_sepatu_2?->format('Y-m-d'),
            'tgl_sepatu_2_fmt'=> $e->ppe?->tgl_sepatu_2?->format('d M Y'),
            'tgl_sepatu_3'    => $e->ppe?->tgl_sepatu_3?->format('Y-m-d'),
            'tgl_sepatu_3_fmt'=> $e->ppe?->tgl_sepatu_3?->format('d M Y'),
            'tgl_helm'          => $e->ppe?->tgl_helm?->format('Y-m-d'),
            'tgl_helm_fmt'      => $e->ppe?->tgl_helm?->format('d M Y'),
            'white_helmet'        => (bool)($e->ppe?->white_helmet ?? false),
            'helmet'              => (bool)($e->ppe?->helmet       ?? false),
            'tgl_helm_orange'     => $e->ppe?->tgl_helm_orange?->format('Y-m-d'),
            'tgl_helm_orange_fmt' => $e->ppe?->tgl_helm_orange?->format('d M Y'),
            'tgl_glass'       => $e->ppe?->tgl_glass?->format('Y-m-d'),
            'tgl_glass_fmt'   => $e->ppe?->tgl_glass?->format('d M Y'),
            'tgl_glass_2'     => $e->ppe?->tgl_glass_2?->format('Y-m-d'),
            'tgl_glass_2_fmt' => $e->ppe?->tgl_glass_2?->format('d M Y'),
            'tgl_vest'        => $e->ppe?->tgl_vest?->format('Y-m-d'),
            'tgl_vest_fmt'    => $e->ppe?->tgl_vest?->format('d M Y'),
            'tgl_ear_plug'      => $e->ppe?->tgl_ear_plug?->format('Y-m-d'),
            'tgl_ear_plug_fmt'  => $e->ppe?->tgl_ear_plug?->format('d M Y'),
            'tgl_ear_plug_2'    => $e->ppe?->tgl_ear_plug_2?->format('Y-m-d'),
            'tgl_ear_plug_2_fmt'=> $e->ppe?->tgl_ear_plug_2?->format('d M Y'),
        ];
    }
}