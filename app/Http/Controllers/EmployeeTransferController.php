<?php
namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\EmployeeTransfer;
use App\Models\Project;
use App\Models\TimesheetMember;
use Illuminate\Http\Request;

// Pindah kantor karyawan. Super-admin memindahkan langsung; akun kantor mengajukan dan
// disetujui/ditolak oleh kantor tujuan (atau super-admin).
class EmployeeTransferController extends Controller
{
    public function index()
    {
        $user  = auth()->user();
        $query = EmployeeTransfer::with(['employee.position', 'fromProject', 'toProject', 'requestedBy', 'approvedBy'])
            ->orderByDesc('created_at');

        // Akun kantor melihat pengajuan yang KELUAR dari kantornya ATAU yang MASUK ke kantornya.
        if (!$user->hasRole('super-admin')) {
            $query->where(fn($q) => $q->where('from_project_id', $user->project_id)->orWhere('to_project_id', $user->project_id));
        }

        $transfers = $query->get()->map(fn($t) => [
            'id'                => $t->id,
            'employee_id'       => $t->employee_id,
            'employee_name'     => $t->employee?->nama_lengkap,
            'employee_badge'    => $t->employee?->id_badge,
            'employee_jabatan'  => $t->employee?->position?->nama_jabatan ?? '-',
            'from_project'      => $t->fromProject?->nama,
            'from_project_kode' => $t->fromProject?->kode,
            'to_project'        => $t->toProject?->nama,
            'to_project_kode'   => $t->toProject?->kode,
            'to_project_id_raw' => $t->to_project_id,
            'status'            => $t->status,
            'catatan'           => $t->catatan,
            'catatan_approval'  => $t->catatan_approval,
            'requested_by'      => $t->requestedBy?->name,
            'approved_by'       => $t->approvedBy?->name,
            'approved_at'       => $t->approved_at?->format('d M Y H:i'),
            'created_at'        => $t->created_at->format('d M Y H:i'),
        ]);

        return response()->json([
            'transfers'     => $transfers,
            'projects'      => Project::where('is_active', true)->orderBy('nama')->get(['id', 'kode', 'nama']),
            'pending_count' => EmployeeTransfer::where('status', 'pending')->count(),
        ]);
    }

    public function pendingCount()
    {
        return response()->json(['count' => EmployeeTransfer::where('status', 'pending')->count()]);
    }

    public function transferDirect(Request $request, Employee $employee)
    {
        if (!auth()->user()->hasRole('super-admin')) {
            return response()->json(['ok' => false, 'message' => 'Tidak memiliki akses.'], 403);
        }

        $data = $request->validate([
            'to_project_id' => 'required|exists:projects,id',
            'catatan'       => 'nullable|string|max:500',
        ]);

        if ($employee->project_id == $data['to_project_id']) {
            $toProject = Project::find($data['to_project_id']);
            return response()->json([
                'ok'      => false,
                'message' => "Karyawan sudah berada di project {$toProject?->nama}. Silakan refresh halaman.",
            ], 422);
        }

        // Cegah double-submit: transfer yang sama sudah disetujui dalam 5 detik terakhir.
        $recentTransfer = EmployeeTransfer::where('employee_id', $employee->id)
            ->where('to_project_id', $data['to_project_id'])
            ->where('status', 'approved')
            ->where('approved_at', '>=', now()->subSeconds(5))
            ->exists();
        if ($recentTransfer) {
            return response()->json([
                'ok'      => false,
                'message' => 'Transfer baru saja dilakukan. Silakan refresh halaman terlebih dahulu.',
            ], 422);
        }

        $this->pindahLangsung($employee, $data['to_project_id'], $data['catatan'] ?? null, 'Transfer langsung oleh Super Admin');

        $toProject = Project::find($data['to_project_id']);
        ActivityLog::record('update', 'Pindah Project', $employee->nama_lengkap, "Transfer langsung: {$employee->nama_lengkap} ke {$toProject?->nama}");

        return response()->json([
            'ok'               => true,
            'message'          => "Karyawan berhasil dipindahkan ke {$toProject?->nama}.",
            'new_project_id'   => (int) $data['to_project_id'],
            'new_project_nama' => $toProject?->nama,
        ]);
    }

    // Banyak karyawan sekaligus. Super admin -> langsung pindah; role lain -> pengajuan massal menunggu approval.
    public function transferBulk(Request $request)
    {
        $user         = auth()->user();
        $isSuperAdmin = $user->hasRole('super-admin');

        $data = $request->validate([
            'employee_ids'   => 'required|array|min:1',
            'employee_ids.*' => 'exists:employees,id',
            'to_project_id'  => 'required|exists:projects,id',
            'catatan'        => 'nullable|string|max:500',
        ]);

        $toProject = Project::find($data['to_project_id']);
        $catatan   = $data['catatan'] ?? null;
        $moved = 0; $requested = 0; $skipped = 0;

        foreach (Employee::whereIn('id', $data['employee_ids'])->get() as $employee) {
            if ($employee->project_id == $data['to_project_id']) { $skipped++; continue; }

            if ($isSuperAdmin) {
                $this->pindahLangsung($employee, $data['to_project_id'], $catatan, 'Transfer massal oleh Super Admin');
                $moved++;
                continue;
            }

            if (!$employee->project_id || $this->adaPengajuanPending($employee)) { $skipped++; continue; }
            $this->buatPengajuan($employee, $data['to_project_id'], $catatan);
            $requested++;
        }

        ActivityLog::record('update', 'Pindah Project', $toProject?->nama, $isSuperAdmin
            ? "Transfer massal {$moved} karyawan ke {$toProject?->nama}"
            : "Pengajuan mutasi massal {$requested} karyawan ke {$toProject?->nama}");

        $message = $isSuperAdmin
            ? "{$moved} karyawan berhasil dipindahkan ke {$toProject?->nama}." . ($skipped ? " {$skipped} dilewati (sudah di project tujuan)." : '')
            : "{$requested} pengajuan mutasi dikirim, menunggu persetujuan dari {$toProject?->nama} atau Super Admin." . ($skipped ? " {$skipped} dilewati." : '');

        return response()->json(['ok' => true, 'message' => $message, 'moved' => $moved, 'requested' => $requested, 'skipped' => $skipped]);
    }

    public function requestTransfer(Request $request, Employee $employee)
    {
        if (auth()->user()->hasRole('super-admin')) {
            return response()->json(['ok' => false, 'message' => 'Super admin gunakan transfer langsung.'], 422);
        }

        $data = $request->validate([
            'to_project_id' => 'required|exists:projects,id',
            'catatan'       => 'nullable|string|max:500',
        ]);

        if (!$employee->project_id) {
            return response()->json(['ok' => false, 'message' => 'Karyawan ini belum memiliki project asal.'], 422);
        }
        if ($employee->project_id == $data['to_project_id']) {
            return response()->json(['ok' => false, 'message' => 'Karyawan sudah di project tujuan.'], 422);
        }
        if ($this->adaPengajuanPending($employee)) {
            return response()->json(['ok' => false, 'message' => 'Sudah ada pengajuan pindah project yang belum diproses untuk karyawan ini.'], 422);
        }

        $this->buatPengajuan($employee, $data['to_project_id'], $data['catatan'] ?? null);

        $toProject = Project::find($data['to_project_id']);
        ActivityLog::record('create', 'Pindah Project', $employee->nama_lengkap, "Pengajuan mutasi: {$employee->nama_lengkap} ke {$toProject?->nama}");

        return response()->json(['ok' => true, 'message' => "Pengajuan mutasi berhasil dikirim. Menunggu persetujuan dari {$toProject?->nama} atau Super Admin."]);
    }

    public function approve(Request $request, EmployeeTransfer $transfer)
    {
        if ($error = $this->cekBolehProses($transfer, 'menyetujui')) return $error;

        $data = $request->validate(['catatan_approval' => 'nullable|string|max:500']);

        $employee = $transfer->employee;
        $transfer->update([
            'status'           => 'approved',
            'approved_by'      => auth()->id(),
            'catatan_approval' => $data['catatan_approval'] ?? null,
            'approved_at'      => now(),
        ]);
        $employee->update(['project_id' => $transfer->to_project_id]);
        $this->pindahkanTimesheetMember($employee, $transfer->from_project_id, $transfer->to_project_id);

        $toProject = Project::find($transfer->to_project_id);
        ActivityLog::record('update', 'Pindah Project', $employee->nama_lengkap, "Approve mutasi: {$employee->nama_lengkap} ke {$toProject?->nama}");

        return response()->json(['ok' => true, 'message' => "Pengajuan disetujui. {$employee->nama_lengkap} dipindahkan ke {$toProject?->nama}."]);
    }

    public function reject(Request $request, EmployeeTransfer $transfer)
    {
        if ($error = $this->cekBolehProses($transfer, 'menolak')) return $error;

        $data = $request->validate(['catatan_approval' => 'nullable|string|max:500']);

        $transfer->update([
            'status'           => 'rejected',
            'approved_by'      => auth()->id(),
            'catatan_approval' => $data['catatan_approval'] ?? null,
            'approved_at'      => now(),
        ]);

        ActivityLog::record('update', 'Pindah Project', $transfer->employee?->nama_lengkap, "Reject mutasi: {$transfer->employee?->nama_lengkap}");

        return response()->json(['ok' => true, 'message' => 'Pengajuan ditolak.']);
    }

    // Yang boleh memproses pengajuan: super admin, atau akun kantor yang kantornya = kantor tujuan.
    private function cekBolehProses(EmployeeTransfer $transfer, string $aksi): ?\Illuminate\Http\JsonResponse
    {
        $user = auth()->user();
        if (!$user->hasRole('super-admin') && $user->project_id != $transfer->to_project_id) {
            return response()->json(['ok' => false, 'message' => "Tidak memiliki akses untuk {$aksi} pengajuan ini."], 403);
        }
        if ($transfer->status !== 'pending') {
            return response()->json(['ok' => false, 'message' => 'Pengajuan ini sudah diproses.'], 422);
        }
        return null;
    }

    // Pindah langsung (super admin): ubah kantor, ikut pindahkan anggota timesheet, catat riwayat sebagai approved.
    private function pindahLangsung(Employee $employee, $toProjectId, ?string $catatan, string $catatanApproval): void
    {
        $fromProjectId = $employee->project_id; // bisa null kalau karyawan belum punya kantor
        $employee->update(['project_id' => $toProjectId]);
        if ($fromProjectId) {
            $this->pindahkanTimesheetMember($employee, $fromProjectId, $toProjectId);
        }

        EmployeeTransfer::create([
            'employee_id'      => $employee->id,
            'from_project_id'  => $fromProjectId,
            'to_project_id'    => $toProjectId,
            'requested_by'     => auth()->id(),
            'approved_by'      => auth()->id(),
            'status'           => 'approved',
            'catatan'          => $catatan,
            'catatan_approval' => $catatanApproval,
            'approved_at'      => now(),
        ]);
    }

    private function pindahkanTimesheetMember(Employee $employee, $fromProjectId, $toProjectId): void
    {
        TimesheetMember::where('id_badge', $employee->id_badge)
            ->where('project_id', $fromProjectId)
            ->update(['project_id' => $toProjectId]);
    }

    private function adaPengajuanPending(Employee $employee): bool
    {
        return EmployeeTransfer::where('employee_id', $employee->id)->where('status', 'pending')->exists();
    }

    private function buatPengajuan(Employee $employee, $toProjectId, ?string $catatan): void
    {
        EmployeeTransfer::create([
            'employee_id'     => $employee->id,
            'from_project_id' => $employee->project_id,
            'to_project_id'   => $toProjectId,
            'requested_by'    => auth()->id(),
            'status'          => 'pending',
            'catatan'         => $catatan,
        ]);
    }
}
