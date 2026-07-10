<?php
// app/Http/Controllers/EmployeeTransferController.php
namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\EmployeeTransfer;
use App\Models\Project;
use App\Models\TimesheetMember;
use Illuminate\Http\Request;

class EmployeeTransferController extends Controller
{
    public function index()
    {
        $user         = auth()->user();
        $isSuperAdmin = $user->hasRole('super-admin');

        $query = EmployeeTransfer::with([
            'employee.position',
            'fromProject',
            'toProject',
            'requestedBy',
            'approvedBy',
        ])->orderByDesc('created_at');

        if (!$isSuperAdmin) {
            // Project user lihat pengajuan yang KELUAR dari project mereka
            // ATAU yang MASUK ke project mereka (supaya bisa tahu ada karyawan yang mau datang)
            $query->where(function($q) use ($user) {
                $q->where('from_project_id', $user->project_id)
                  ->orWhere('to_project_id', $user->project_id);
            });
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

        $projects     = Project::where('is_active', true)->orderBy('nama')->get(['id', 'kode', 'nama']);
        $pendingCount = EmployeeTransfer::where('status', 'pending')->count();

        return response()->json([
            'transfers'     => $transfers,
            'projects'      => $projects,
            'pending_count' => $pendingCount,
        ]);
    }

    public function pendingCount()
    {
        $count = EmployeeTransfer::where('status', 'pending')->count();
        return response()->json(['count' => $count]);
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

        // ── GUARD 1: Karyawan sudah di project tujuan ──
        if ($employee->project_id == $data['to_project_id']) {
            $toProject = Project::find($data['to_project_id']);
            return response()->json([
                'ok'      => false,
                'message' => "Karyawan sudah berada di project {$toProject?->nama}. Silakan refresh halaman.",
            ], 422);
        }

        // ── GUARD 2: Cegah double-submit dalam 30 detik ──
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

        $fromProjectId = $employee->project_id; // bisa null kalau belum punya project

        $employee->update(['project_id' => $data['to_project_id']]);

        // Pindahkan timesheet member hanya kalau ada project asal
        if ($fromProjectId) {
            TimesheetMember::where('id_badge', $employee->id_badge)
                ->where('project_id', $fromProjectId)
                ->update(['project_id' => $data['to_project_id']]);
        }

        EmployeeTransfer::create([
            'employee_id'      => $employee->id,
            'from_project_id'  => $fromProjectId, // nullable — karyawan baru tanpa project asal
            'to_project_id'    => $data['to_project_id'],
            'requested_by'     => auth()->id(),
            'approved_by'      => auth()->id(),
            'status'           => 'approved',
            'catatan'          => $data['catatan'] ?? null,
            'catatan_approval' => 'Transfer langsung oleh Super Admin',
            'approved_at'      => now(),
        ]);

        $toProject = Project::find($data['to_project_id']);
        ActivityLog::record(
            'update', 'Pindah Project',
            $employee->nama_lengkap,
            "Transfer langsung: {$employee->nama_lengkap} ke {$toProject?->nama}"
        );

        return response()->json([
            'ok'      => true,
            'message' => "Karyawan berhasil dipindahkan ke {$toProject?->nama}.",
            // Kembalikan project terbaru supaya frontend bisa update state
            'new_project_id'   => (int) $data['to_project_id'],
            'new_project_nama' => $toProject?->nama,
        ]);
    }

    public function requestTransfer(Request $request, Employee $employee)
    {
        $user = auth()->user();

        if ($user->hasRole('super-admin')) {
            return response()->json(['ok' => false, 'message' => 'Super admin gunakan transfer langsung.'], 422);
        }

        $data = $request->validate([
            'to_project_id' => 'required|exists:projects,id',
            'catatan'       => 'nullable|string|max:500',
        ]);

        if (!$employee->project_id) {
            return response()->json([
                'ok'      => false,
                'message' => 'Karyawan ini belum memiliki project asal.',
            ], 422);
        }

        $fromProjectId = $employee->project_id;

        if ($fromProjectId == $data['to_project_id']) {
            return response()->json(['ok' => false, 'message' => 'Karyawan sudah di project tujuan.'], 422);
        }

        $existing = EmployeeTransfer::where('employee_id', $employee->id)
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            return response()->json(['ok' => false, 'message' => 'Sudah ada pengajuan pindah project yang belum diproses untuk karyawan ini.'], 422);
        }

        EmployeeTransfer::create([
            'employee_id'     => $employee->id,
            'from_project_id' => $fromProjectId,
            'to_project_id'   => $data['to_project_id'],
            'requested_by'    => $user->id,
            'status'          => 'pending',
            'catatan'         => $data['catatan'] ?? null,
        ]);

        $toProject = Project::find($data['to_project_id']);
        ActivityLog::record(
            'create', 'Pindah Project',
            $employee->nama_lengkap,
            "Pengajuan mutasi: {$employee->nama_lengkap} ke {$toProject?->nama}"
        );

        return response()->json(['ok' => true, 'message' => 'Pengajuan mutasi berhasil dikirim. Menunggu persetujuan Super Admin.']);
    }

    public function approve(Request $request, EmployeeTransfer $transfer)
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole('super-admin');

        // Boleh approve: super admin ATAU project user yang projectnya = project tujuan
        $isDestinationUser = !$isSuperAdmin && $user->project_id == $transfer->to_project_id;

        if (!$isSuperAdmin && !$isDestinationUser) {
            return response()->json(['ok' => false, 'message' => 'Tidak memiliki akses untuk menyetujui pengajuan ini.'], 403);
        }

        if ($transfer->status !== 'pending') {
            return response()->json(['ok' => false, 'message' => 'Pengajuan ini sudah diproses.'], 422);
        }

        $data = $request->validate([
            'catatan_approval' => 'nullable|string|max:500',
        ]);

        $employee      = $transfer->employee;
        $fromProjectId = $transfer->from_project_id;
        $toProjectId   = $transfer->to_project_id;

        $transfer->update([
            'status'           => 'approved',
            'approved_by'      => auth()->id(),
            'catatan_approval' => $data['catatan_approval'] ?? null,
            'approved_at'      => now(),
        ]);

        $employee->update(['project_id' => $toProjectId]);

        TimesheetMember::where('id_badge', $employee->id_badge)
            ->where('project_id', $fromProjectId)
            ->update(['project_id' => $toProjectId]);

        $toProject = Project::find($toProjectId);
        ActivityLog::record(
            'update', 'Pindah Project',
            $employee->nama_lengkap,
            "Approve mutasi: {$employee->nama_lengkap} ke {$toProject?->nama}"
        );

        return response()->json(['ok' => true, 'message' => "Pengajuan disetujui. {$employee->nama_lengkap} dipindahkan ke {$toProject?->nama}."]);
    }

    public function reject(Request $request, EmployeeTransfer $transfer)
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole('super-admin');

        // Boleh reject: super admin ATAU project user yang projectnya = project tujuan
        $isDestinationUser = !$isSuperAdmin && $user->project_id == $transfer->to_project_id;

        if (!$isSuperAdmin && !$isDestinationUser) {
            return response()->json(['ok' => false, 'message' => 'Tidak memiliki akses untuk menolak pengajuan ini.'], 403);
        }

        if ($transfer->status !== 'pending') {
            return response()->json(['ok' => false, 'message' => 'Pengajuan ini sudah diproses.'], 422);
        }

        $data = $request->validate([
            'catatan_approval' => 'nullable|string|max:500',
        ]);

        $transfer->update([
            'status'           => 'rejected',
            'approved_by'      => auth()->id(),
            'catatan_approval' => $data['catatan_approval'] ?? null,
            'approved_at'      => now(),
        ]);

        ActivityLog::record(
            'update', 'Pindah Project',
            $transfer->employee?->nama_lengkap,
            "Reject mutasi: {$transfer->employee?->nama_lengkap}"
        );

        return response()->json(['ok' => true, 'message' => 'Pengajuan ditolak.']);
    }
}