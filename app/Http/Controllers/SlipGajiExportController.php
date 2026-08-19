<?php
namespace App\Http\Controllers;

use App\Models\EmployeePayroll;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SlipGajiExportController extends Controller
{
    // ── Halaman print (Inertia) ──────────────────────────────
    // Memakai PayrollController::getSlipData() — satu-satunya sumber perhitungan
    // gaji, sama dengan yang dipakai halaman Data Gaji & Slip Gaji.
    public function print(Request $request, $payrollId, PayrollController $payrollController)
    {
        $payroll  = EmployeePayroll::with(['employee.position', 'employee.project'])->findOrFail($payrollId);
        $employee = $payroll->employee;
        $slip     = $payrollController->getSlipData($payroll->employee_id, $payroll->tahun, $payroll->bulan);

        return Inertia::render('SlipGaji/Print', [
            'slip'     => $slip,
            'employee' => [
                'id_badge'       => $employee->id_badge,
                'nama_lengkap'   => $employee->nama_lengkap,
                'jabatan'        => $employee->position?->nama_jabatan ?? '-',
                'project_nama'   => $employee->project?->nama,
                'no_rekening'    => $employee->no_rekening,
                'ptkp'           => $employee->ptkp,
                'tanggal_masuk'  => $employee->tanggal_masuk?->format('d M Y'),
            ],
            'periode' => [
                'tahun' => $payroll->tahun,
                'bulan' => $payroll->bulan,
            ],
        ]);
    }

    // ── Export Excel ─────────────────────────────────────────
    // Delegasikan ke PayrollController::exportSlipExcel() — supaya file Excel
    // yang dihasilkan persis sama (satu sumber) dengan export dari halaman Slip Gaji.
    public function exportExcel($payrollId, PayrollController $payrollController)
    {
        $payroll = EmployeePayroll::findOrFail($payrollId);

        $forwarded = Request::create('/timesheet/slip-gaji/export-excel', 'GET', [
            'tahun'       => $payroll->tahun,
            'bulan'       => $payroll->bulan,
            'employee_id' => $payroll->employee_id,
        ]);

        return $payrollController->exportSlipExcel($forwarded);
    }
}
