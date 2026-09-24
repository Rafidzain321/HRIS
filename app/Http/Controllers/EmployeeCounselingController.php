<?php
namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\EmployeeCounseling;
use App\Models\KpiAppraisal;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmployeeCounselingController extends Controller
{
    // Konseling itu sensitif (kadang menyangkut masalah pribadi karyawan), jadi tidak dibuka
    // ke semua yang punya akses "lihat data karyawan" seperti biasa — cuma HR/super-admin atau
    // atasan langsung karyawan yang bersangkutan yang boleh kelola/lihat catatannya.
    private function canManage(int $employeeId): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        if ($this->isAdminSettings()) return true;
        if (!$user->employee_id) return false;

        return Employee::where('id', $employeeId)->where('atasan_id', $user->employee_id)->exists();
    }

    // Null = lihat semua (HR/super-admin). Selain itu, cuma bawahan langsung.
    private function scopedEmployeeIds(): ?array
    {
        $user = auth()->user();
        if ($this->isAdminSettings()) return null;
        if (!$user->employee_id) return [];

        return Employee::where('atasan_id', $user->employee_id)->pluck('id')->toArray();
    }

    public function index(Request $request)
    {
        $scopedIds = $this->scopedEmployeeIds();

        $employeeModels = $this->applyProjectFilter(Employee::aktif())
            ->when($scopedIds !== null, fn ($q) => $q->whereIn('id', $scopedIds))
            ->with('position')
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'position_id']);

        // Hasil KPI (final) terbaru per karyawan — dipakai sebagai acuan pertimbangan konseling
        // (mis. predikat Kurang/Cukup jadi sinyal karyawan itu mungkin perlu dibina), sesuai poin
        // 12 SOP: "data hasil KPI dapat menjadi acuan dalam menentukan kebutuhan konseling".
        $latestKpiByEmployee = KpiAppraisal::where('status', 'submitted')
            ->whereIn('employee_id', $employeeModels->pluck('id'))
            ->orderByDesc('tahun')->orderByDesc('semester')
            ->get()
            ->unique('employee_id')
            ->keyBy('employee_id');

        $employees = $employeeModels->map(function ($e) use ($latestKpiByEmployee) {
            $kpi = $latestKpiByEmployee->get($e->id);
            return [
                'id'           => $e->id,
                'nama_lengkap' => $e->nama_lengkap,
                'jabatan'      => $e->position?->nama_jabatan ?? '-',
                'kpi_terakhir' => $kpi ? [
                    'total_nilai' => $kpi->total_nilai,
                    'predikat'    => $kpi->predikat,
                    'periode'     => "S{$kpi->semester} {$kpi->tahun}",
                ] : null,
            ];
        });

        $sessions = EmployeeCounseling::whereIn('employee_id', $employees->pluck('id'))
            ->orderByDesc('tanggal_konseling')
            ->get()
            ->map(fn ($c) => [
                'id'                => $c->id,
                'employee_id'       => $c->employee_id,
                'tanggal_konseling' => $c->tanggal_konseling->format('Y-m-d'),
                'kategori'          => $c->kategori,
                'catatan'           => $c->catatan,
                'tindak_lanjut'     => $c->tindak_lanjut,
                'status'            => $c->status,
                'ditangani_oleh'    => $c->ditangani_oleh,
            ]);

        return Inertia::render('Konseling/Index', [
            'employees' => $employees,
            'sessions'  => $sessions,
            'can_edit'  => $this->isAdminSettings() || (auth()->user()->employee_id && Employee::where('atasan_id', auth()->user()->employee_id)->exists()),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id'       => 'required|exists:employees,id',
            'tanggal_konseling' => 'required|date',
            'kategori'          => 'required|in:kinerja,disiplin,pribadi,karir,lainnya',
            'catatan'           => 'required|string|max:2000',
            'tindak_lanjut'     => 'nullable|string|max:1000',
            'status'            => 'nullable|in:selesai,perlu_tindak_lanjut',
        ]);

        if (!$this->canManage((int) $data['employee_id'])) {
            return back()->withErrors(['employee_id' => 'Kamu tidak memiliki akses untuk mencatat konseling karyawan ini.']);
        }

        $counseling = EmployeeCounseling::create([
            ...$data,
            'status'         => $data['status'] ?? 'selesai',
            'ditangani_oleh' => auth()->user()?->name,
        ]);

        ActivityLog::record('create', 'Konseling', $counseling->employee->nama_lengkap ?? '-', "Catat sesi konseling ({$data['kategori']}) tanggal {$data['tanggal_konseling']}");

        return back()->with('success', 'Sesi konseling berhasil dicatat.');
    }

    public function markSelesai(EmployeeCounseling $counseling)
    {
        if (!$this->canManage($counseling->employee_id)) {
            return back()->withErrors(['status' => 'Kamu tidak memiliki akses untuk mengubah catatan ini.']);
        }

        $counseling->update(['status' => 'selesai']);
        ActivityLog::record('update', 'Konseling', $counseling->employee->nama_lengkap ?? '-', 'Tindak lanjut konseling ditandai selesai');

        return back()->with('success', 'Ditandai selesai.');
    }

    public function destroy(EmployeeCounseling $counseling)
    {
        if (!$this->canManage($counseling->employee_id)) {
            return back()->withErrors(['employee_id' => 'Kamu tidak memiliki akses untuk menghapus catatan ini.']);
        }

        $nama = $counseling->employee->nama_lengkap ?? '-';
        $counseling->delete();
        ActivityLog::record('delete', 'Konseling', $nama, 'Catatan konseling dihapus');

        return back()->with('success', 'Catatan konseling berhasil dihapus.');
    }
}
