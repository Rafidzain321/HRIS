<?php
namespace App\Http\Middleware;
use App\Models\Employee;
use App\Models\EmployeePayroll;
use App\Models\EmployeeTransfer;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
class CheckMenuPermission
{
    // Parameter route berupa ID biasa (bukan model binding) → model yang diwakilinya.
    const PARAM_ID = [
        'id'         => Employee::class,        // /employees/{id}/history
        'employeeId' => Employee::class,        // /overtime-custom/{employeeId}
        'payrollId'  => EmployeePayroll::class, // /slip-gaji/{payrollId}/...
    ];

    public function handle(Request $request, Closure $next, string $menu, string $action = 'view'): mixed
    {
        $user = $request->user();
        $permission = "{$action}-{$menu}";
        if (!$user || !$user->can($permission)) {
            abort(403, 'Anda tidak memiliki izin untuk mengakses halaman ini.');
        }
        // Hak edit cuma berlaku untuk project asal user sendiri (project_id) — bukan project
        // manapun yang lagi ditampilkan lewat switch-project (activeProjectId() bisa berpindah
        // untuk user multi-project via project_ids). Supaya user yang dikasih akses lihat ke
        // banyak project tetap read-only di project selain miliknya sendiri.
        if ($action === 'edit' && !$user->hasRole('super-admin') && !$user->hasRole('viewer')) {
            $projectIds = $user->project_ids ? json_decode($user->project_ids, true) : null;
            if (is_array($projectIds) && count($projectIds) > 1) {
                $activeProjectId = session('active_project_kode') ?: ($projectIds[0] ?? null);
                if (!in_array((int) $activeProjectId, $user->fullEditProjectIds())) {
                    abort(403, 'Anda hanya memiliki akses lihat (bukan edit) untuk project ini.');
                }
            }
        }

        // Data yang dibuka/diubah lewat URL (atau employee_id di form) harus milik kantor yang boleh
        // diakses user — tanpa ini, HRD satu kantor bisa membuka/mengubah karyawan kantor lain cukup
        // dengan mengganti ID di URL. edit = kantor full-edit, view = kantor yang boleh dilihat.
        $akses = $user->aksesKantor($action === 'edit'); // null = semua kantor
        if ($akses !== null) {
            $nilai = $request->route()?->parameters() ?? [];
            if (!is_array($request->input('employee_id'))) $nilai['employee_id'] = $request->input('employee_id');
            foreach ($nilai as $nama => $v) {
                $kantor = $this->kantorDari($nama, $v);
                if ($kantor !== null && !in_array((int) $kantor, $akses, true)) {
                    abort(403, 'Data ini milik kantor lain — Anda tidak memiliki akses.');
                }
            }
        }

        return $next($request);
    }

    // Kantor (project_id) pemilik data, null = tidak terkait kantor / tidak dicek.
    private function kantorDari(string $nama, $v): ?int
    {
        if ($v === null || $v === '') return null;
        // Mutasi disetujui oleh kantor tujuan — aksesnya sudah diatur di EmployeeTransferController.
        if ($v instanceof EmployeeTransfer) return null;

        if (!$v instanceof Model) {
            $kelas = $nama === 'employee_id' ? Employee::class : (self::PARAM_ID[$nama] ?? null);
            if (!$kelas || !is_numeric($v)) return null;
            $v = $kelas::query()->find($v);
            if (!$v) return null;
        }

        $attr = $v->getAttributes();
        if (array_key_exists('project_id', $attr)) return $attr['project_id'];
        if (array_key_exists('employee_id', $attr) && $attr['employee_id']) {
            return Employee::withTrashed()->whereKey($attr['employee_id'])->value('project_id');
        }
        return null;
    }
}
