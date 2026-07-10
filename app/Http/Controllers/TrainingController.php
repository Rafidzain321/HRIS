<?php
namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\TrainingType;
use App\Models\EmployeeTraining;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TrainingController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search', '');
        $typeFilter = $request->get('type', '');
        $statusFilter = $request->get('status', '');

        $pid = $this->activeProjectId();
        $types = TrainingType::where('is_active', true)->orderBy('urutan')
            ->withCount([
                'trainings' => fn($q) => $q->when(
                    $pid,
                    fn($q2) =>
                    $q2->whereHas('employee', fn($eq) => $eq->where('project_id', $pid))
                )
            ])
            ->get()
            ->map(fn($t) => [
                'id' => $t->id,
                'nama' => $t->nama,
                'trainings_count' => $t->trainings_count,
                'masa_berlaku_label' => $t->masa_berlaku_label,
                'has_expired' => $t->has_expired,
                'has_nilai' => $t->has_nilai,
                'masa_berlaku_tahun' => $t->masa_berlaku_tahun,
            ]);

        $query = EmployeeTraining::with(['employee.position', 'trainingType'])
            ->when($pid, fn($q) => $q->whereHas('employee', fn($eq) => $eq->where('project_id', $pid)))
            ->when($search, fn($q) => $q->whereHas(
                'employee',
                fn($eq) => $eq
                    ->where('nama_lengkap', 'like', "%$search%")
                    ->orWhere('no_ktp', 'like', "%$search%")
            ))
            ->when($typeFilter, fn($q) => $q->where('training_type_id', $typeFilter));

        $mapped = $query->get()->map(fn($t) => [
            'id' => $t->id,
            'employee_id' => $t->employee_id,
            'no_ktp' => $t->employee?->no_ktp,
            'id_badge' => $t->employee?->id_badge,
            'nama_lengkap' => $t->employee?->nama_lengkap,
            'jabatan' => $t->employee?->position?->nama_jabatan ?? '-',
            'jenis' => $t->trainingType?->nama,
            'tanggal' => $t->tanggal?->format('d M Y'),
            'nama_trainer' => $t->nama_trainer,
            'nilai' => $t->nilai,
            'status' => $t->status,
            'expired_date' => $t->expired_date?->format('d M Y'),
            'auto_expired' => $t->auto_expired,
            'auto_expired_fmt' => $t->auto_expired ? \Carbon\Carbon::parse($t->auto_expired)->format('d M Y') : null,
            'status_expired' => $t->status_expired,
            'sisa_hari' => $t->sisa_hari,
            'catatan' => $t->catatan,
        ]);

        $expiredCount  = $mapped->where('status_expired', 'expired')->count();
        $warningCount  = $mapped->where('status_expired', 'warning')->count();
        $validCount    = $mapped->where('status_expired', 'valid')->count();
        $lifetimeCount = $mapped->where('status_expired', 'lifetime')->count();
        $noDeteCount   = $mapped->where('status_expired', 'no_date')->count();

        $allTrainings = $statusFilter
            ? $mapped->filter(fn($t) => $t['status_expired'] === $statusFilter)->values()
            : $mapped->values();

        return Inertia::render('Training/Index', [
            'trainings' => $allTrainings->values(),
            'types' => $types,
            'filters' => compact('search', 'typeFilter', 'statusFilter'),
            'stats' => compact('expiredCount', 'warningCount', 'validCount', 'lifetimeCount', 'noDeteCount'),
        ]);
    }

    public function forEmployee(Employee $employee)
    {
        $trainings = EmployeeTraining::where('employee_id', $employee->id)
            ->with('trainingType')->orderBy('training_type_id')->get()
            ->map(fn($t) => [
                'id' => $t->id,
                'training_type_id' => $t->training_type_id,
                'jenis' => $t->trainingType?->nama,
                'has_nilai' => $t->trainingType?->has_nilai,
                'has_expired' => $t->trainingType?->has_expired,
                'masa_berlaku_tahun' => $t->trainingType?->masa_berlaku_tahun,
                'masa_berlaku_label' => $t->trainingType?->masa_berlaku_label,
                'tanggal' => $t->tanggal?->format('Y-m-d'),
                'tanggal_fmt' => $t->tanggal?->format('d M Y'),
                'nama_trainer' => $t->nama_trainer,
                'nilai' => $t->nilai,
                'status' => $t->status,
                'expired_date' => $t->expired_date?->format('Y-m-d'),
                'auto_expired' => $t->auto_expired,
                'auto_expired_fmt' => $t->auto_expired ? \Carbon\Carbon::parse($t->auto_expired)->format('d M Y') : null,
                'status_expired' => $t->status_expired,
                'sisa_hari' => $t->sisa_hari,
                'catatan' => $t->catatan,
            ]);
        return response()->json($trainings);
    }

    public function store(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'training_type_id' => 'required|exists:training_types,id',
            'tanggal' => 'nullable|date',
            'nama_trainer' => 'nullable|string|max:150',
            'nilai' => 'nullable|string|max:20',
            'status' => 'nullable|string|max:20',
            'expired_date' => 'nullable|date',
            'catatan' => 'nullable|string|max:500',
        ]);

        $exists = EmployeeTraining::where('employee_id', $employee->id)
            ->where('training_type_id', $data['training_type_id'])->exists();
        if ($exists)
            return response()->json(['message' => 'Karyawan sudah memiliki training ini.'], 422);

        $training = EmployeeTraining::create(['employee_id' => $employee->id, 'input_by' => auth()->id(), ...$data]);
        $type = TrainingType::find($data['training_type_id']);
        ActivityLog::record('create', 'Training', $employee->nama_lengkap, "Tambah training: {$type?->nama}");
        return response()->json(['message' => 'Training berhasil ditambahkan.', 'id' => $training->id]);
    }

    public function update(Request $request, EmployeeTraining $training)
    {
        $data = $request->validate([
            'tanggal' => 'nullable|date',
            'nama_trainer' => 'nullable|string|max:150',
            'nilai' => 'nullable|string|max:20',
            'status' => 'nullable|string|max:20',
            'expired_date' => 'nullable|date',
            'catatan' => 'nullable|string|max:500',
        ]);
        $training->update($data);
        ActivityLog::record('update', 'Training', $training->employee?->nama_lengkap, "Update training: {$training->trainingType?->nama}");
        return response()->json(['message' => 'Training berhasil diperbarui.']);
    }

    public function destroy(EmployeeTraining $training)
    {
        $nama = $training->trainingType?->nama;
        $emp = $training->employee?->nama_lengkap;
        $training->delete();
        ActivityLog::record('delete', 'Training', $emp, "Hapus training: {$nama}");
        return response()->json(['message' => 'Training berhasil dihapus.']);
    }

    public function storeType(Request $request)
    {
        $data = $request->validate([
            'nama' => 'required|string|max:150|unique:training_types,nama',
            'deskripsi' => 'nullable|string|max:300',
            'masa_berlaku_tahun' => 'nullable|integer|min:1|max:5',
            'has_nilai' => 'boolean',
        ]);
        $t = TrainingType::create([...$data, 'has_expired' => isset($data['masa_berlaku_tahun']), 'is_active' => true, 'urutan' => TrainingType::max('urutan') + 1]);
        ActivityLog::record('create', 'Jenis Training', $t->nama);
        return back()->with('success', "Jenis training \"{$t->nama}\" berhasil ditambahkan.");
    }

    public function updateType(Request $request, TrainingType $trainingType)
    {
        $data = $request->validate([
            'nama' => "required|string|max:150|unique:training_types,nama,{$trainingType->id}",
            'deskripsi' => 'nullable|string|max:300',
            'masa_berlaku_tahun' => 'nullable|integer|min:1|max:5',
            'has_nilai' => 'boolean',
        ]);

        $oldMasaBerlaku = $trainingType->masa_berlaku_tahun;
        $newMasaBerlaku = $data['masa_berlaku_tahun'] ?? null;

        $trainingType->update([...$data, 'has_expired' => isset($data['masa_berlaku_tahun'])]);

        // Recalculate expired_date semua employee_trainings terkait
        // kalau masa_berlaku_tahun berubah
        if ($oldMasaBerlaku !== $newMasaBerlaku) {
            $trainings = EmployeeTraining::where('training_type_id', $trainingType->id)
                ->whereNotNull('tanggal')
                ->get();

            foreach ($trainings as $t) {
                if ($newMasaBerlaku) {
                    // Ada masa berlaku — hitung expired_date dari tanggal + tahun
                    $expiredDate = \Carbon\Carbon::parse($t->tanggal)
                        ->addYears((int) $newMasaBerlaku)
                        ->format('Y-m-d');
                    $t->update(['expired_date' => $expiredDate]);
                } else {
                    // Seumur hidup — kosongkan expired_date
                    $t->update(['expired_date' => null]);
                }
            }
        }

        ActivityLog::record('update', 'Jenis Training', $trainingType->nama,
            "Update jenis training: {$trainingType->nama}" .
            ($oldMasaBerlaku !== $newMasaBerlaku
                ? " | Masa berlaku: {$oldMasaBerlaku} → {$newMasaBerlaku} tahun ({$trainings->count()} record diperbarui)"
                : "")
        );

        return back()->with('success', "Jenis training berhasil diperbarui." .
            ($oldMasaBerlaku !== $newMasaBerlaku
                ? " {$trainings->count()} data training karyawan telah diperbarui."
                : "")
        );
    }

    public function destroyType(TrainingType $trainingType)
    {
        $count = $trainingType->trainings()->count();
        if ($count > 0)
            return back()->with('error', "Tidak bisa hapus — masih dipakai oleh {$count} karyawan.");
        $nama = $trainingType->nama;
        $trainingType->delete();
        ActivityLog::record('delete', 'Jenis Training', $nama);
        return back()->with('success', "Jenis training \"{$nama}\" berhasil dihapus.");
    }
}