<?php
namespace App\Http\Middleware;

use App\Models\ClientProject;
use App\Models\Employee;
use App\Models\EmployeePayroll;
use App\Models\EmployeeTransfer;
use App\Models\Project;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

// Cegah akses silang kantor: data karyawan (atau data turunannya: dokumen, SP, cuti, training, KPI, dll.)
// dan data milik kantor (equipment, driver, CCPM, ...) cuma boleh dibuka oleh user yang punya akses ke
// kantor tsb, dan cuma boleh DIUBAH kalau kantornya full-edit. Dicek dari parameter route & input employee_id(s).
class ProjectAccessMiddleware
{
    // Model yang punya aturan akses sendiri / bukan data kantor.
    const DIKECUALIKAN = [EmployeeTransfer::class, Project::class, User::class, ClientProject::class];

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user || !$request->route()) return $next($request);

        $edit    = !in_array($request->method(), ['GET', 'HEAD', 'OPTIONS']);
        $allowed = $user->aksesKantor($edit);
        if ($allowed === null) return $next($request);

        $kantor = [];
        foreach ($request->route()->parameters() as $name => $value) {
            $kantor[] = $this->kantorDariParameter($name, $value);
        }
        if ($edit) {
            $ids = array_filter([$request->input('employee_id'), ...(array) $request->input('employee_ids', [])], fn($v) => is_scalar($v) && $v !== '');
            if ($ids) array_push($kantor, ...Employee::whereIn('id', $ids)->pluck('project_id')->all());
        }

        foreach ($kantor as $pid) {
            if ($pid === false) continue;
            if (!in_array((int) $pid, $allowed, true)) {
                abort(403, $edit
                    ? 'Kamu tidak punya akses mengubah data kantor ini.'
                    : 'Kamu tidak punya akses ke data kantor ini.');
            }
        }
        return $next($request);
    }

    // Kembalikan project_id (kantor) pemilik data, atau false kalau parameter tidak terkait kantor.
    private function kantorDariParameter(string $name, $value)
    {
        if ($value instanceof Model) {
            if (in_array(get_class($value), self::DIKECUALIKAN, true)) return false;
            if ($value instanceof Employee) return $value->project_id;
            $attr = $value->getAttributes();
            if (!empty($attr['employee_id'])) return Employee::whereKey($attr['employee_id'])->value('project_id') ?? false;
            if (array_key_exists('project_id', $attr) && $attr['project_id']) return $attr['project_id'];
            return false;
        }
        // Parameter berupa ID mentah (tidak di-bind ke model).
        if (!is_numeric($value)) return false;
        return match ($name) {
            'id', 'employeeId' => Employee::whereKey($value)->value('project_id') ?? false,
            'payrollId'        => ($eid = EmployeePayroll::whereKey($value)->value('employee_id')) ? (Employee::whereKey($eid)->value('project_id') ?? false) : false,
            default            => false,
        };
    }
}
