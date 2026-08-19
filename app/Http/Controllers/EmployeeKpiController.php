<?php
// app/Http/Controllers/EmployeeKpiController.php
namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\EmployeeKpiIndicator;
use App\Models\EmployeeKpiScore;
use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmployeeKpiController extends Controller
{
    // KPI khusus untuk karyawan Head Office — tidak terpengaruh project aktif user.
    public function index(Request $request)
    {
        $hoProjectId = Project::where('kode', 'ho')->value('id');

        $bulanSekarang = (int) now()->month;
        $tahun    = (int) $request->get('tahun', now()->year);
        $semester = (int) $request->get('semester', $bulanSekarang <= 6 ? 1 : 2);

        $employees = Employee::aktif()
            ->where('project_id', $hoProjectId)
            ->with('position')
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'position_id'])
            ->map(fn ($e) => [
                'id'           => $e->id,
                'nama_lengkap' => $e->nama_lengkap,
                'jabatan'      => $e->position?->nama_jabatan ?? '-',
            ]);

        $indicators = EmployeeKpiIndicator::where('aktif', true)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->orderBy('urutan')
            ->get()
            ->map(fn ($i) => [
                'id'             => $i->id,
                'employee_id'    => $i->employee_id,
                'nama_indikator' => $i->nama_indikator,
                'bobot'          => $i->bobot,
                'urutan'         => $i->urutan,
            ]);

        $scores = EmployeeKpiScore::whereIn('indicator_id', $indicators->pluck('id'))
            ->where('tahun', $tahun)
            ->where('semester', $semester)
            ->get(['id', 'indicator_id', 'skor', 'catatan']);

        return Inertia::render('Kpi/Index', [
            'employees'  => $employees,
            'indicators' => $indicators,
            'scores'     => $scores,
            'tahun'      => $tahun,
            'semester'   => $semester,
        ]);
    }

    public function storeIndicator(Request $request)
    {
        if ($this->isViewer()) {
            return response()->json(['ok' => false, 'message' => 'Viewer tidak memiliki akses.'], 403);
        }

        $data = $request->validate([
            'employee_id'    => 'required|exists:employees,id',
            'nama_indikator' => 'required|string|max:255',
            'bobot'          => 'required|numeric|min:0.01|max:100',
        ]);

        $urutan = (int) (EmployeeKpiIndicator::where('employee_id', $data['employee_id'])->max('urutan') ?? 0) + 1;

        $indicator = EmployeeKpiIndicator::create([
            'employee_id'    => $data['employee_id'],
            'nama_indikator' => $data['nama_indikator'],
            'bobot'          => $data['bobot'],
            'urutan'         => $urutan,
            'aktif'          => true,
        ]);

        ActivityLog::record('create', 'KPI', $indicator->employee->nama_lengkap ?? '-', "Tambah indikator: {$data['nama_indikator']} ({$data['bobot']}%)");

        return response()->json(['ok' => true, 'indicator' => $indicator]);
    }

    public function updateIndicator(Request $request, EmployeeKpiIndicator $indicator)
    {
        if ($this->isViewer()) {
            return response()->json(['ok' => false, 'message' => 'Viewer tidak memiliki akses.'], 403);
        }

        $data = $request->validate([
            'nama_indikator' => 'required|string|max:255',
            'bobot'          => 'required|numeric|min:0.01|max:100',
        ]);

        $indicator->update($data);

        return response()->json(['ok' => true]);
    }

    public function destroyIndicator(EmployeeKpiIndicator $indicator)
    {
        if ($this->isViewer()) {
            return response()->json(['ok' => false, 'message' => 'Viewer tidak memiliki akses.'], 403);
        }

        // Kalau sudah pernah dinilai, jangan dihapus permanen — cuma disembunyikan
        // supaya riwayat skor semester lama tetap utuh.
        if ($indicator->scores()->exists()) {
            $indicator->update(['aktif' => false]);
        } else {
            $indicator->delete();
        }

        return response()->json(['ok' => true]);
    }

    public function saveScore(Request $request)
    {
        if ($this->isViewer()) {
            return response()->json(['ok' => false, 'message' => 'Viewer tidak memiliki akses.'], 403);
        }

        $data = $request->validate([
            'indicator_id' => 'required|exists:employee_kpi_indicators,id',
            'tahun'        => 'required|integer',
            'semester'     => 'required|integer|in:1,2',
            'skor'         => 'required|numeric|min:0|max:100',
            'catatan'      => 'nullable|string|max:1000',
        ]);

        $score = EmployeeKpiScore::updateOrCreate(
            ['indicator_id' => $data['indicator_id'], 'tahun' => $data['tahun'], 'semester' => $data['semester']],
            ['skor' => $data['skor'], 'catatan' => $data['catatan'] ?? null, 'dibuat_oleh' => auth()->user()?->name ?? 'System']
        );

        return response()->json(['ok' => true, 'score' => $score]);
    }

    // Ringkasan skor akhir tiap karyawan untuk 1 semester tertentu — dipakai tab Ringkasan.
    public function summary(Request $request)
    {
        $hoProjectId = Project::where('kode', 'ho')->value('id');
        $tahun    = (int) $request->get('tahun', now()->year);
        $semester = (int) $request->get('semester', 1);

        $employees = Employee::aktif()->where('project_id', $hoProjectId)->with('position')->orderBy('nama_lengkap')->get();

        $result = $employees->map(function ($e) use ($tahun, $semester) {
            $indicators = $e->kpiIndicators()->where('aktif', true)->orderBy('urutan')->get();
            $totalBobot = $indicators->sum('bobot');
            $skorAkhir  = null;
            $items = $indicators->map(function ($i) use ($tahun, $semester) {
                $score = $i->scores()->where('tahun', $tahun)->where('semester', $semester)->first();
                return [
                    'nama_indikator' => $i->nama_indikator,
                    'bobot'          => $i->bobot,
                    'skor'           => $score?->skor,
                ];
            });
            if ($totalBobot > 0 && $items->every(fn ($i) => $i['skor'] !== null)) {
                $skorAkhir = round($items->sum(fn ($i) => $i['skor'] * $i['bobot'] / 100), 2);
            }
            return [
                'employee_id'   => $e->id,
                'nama_lengkap'  => $e->nama_lengkap,
                'jabatan'       => $e->position?->nama_jabatan ?? '-',
                'total_bobot'   => $totalBobot,
                'skor_akhir'    => $skorAkhir,
                'jml_indikator' => $indicators->count(),
            ];
        });

        return response()->json(['tahun' => $tahun, 'semester' => $semester, 'rows' => $result]);
    }
}
