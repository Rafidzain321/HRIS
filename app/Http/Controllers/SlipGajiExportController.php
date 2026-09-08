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
        $payroll = EmployeePayroll::findOrFail($payrollId);
        $slip    = $payrollController->getSlipData($payroll->employee_id, $payroll->tahun, $payroll->bulan);

        return Inertia::render('SlipGaji/Print', [
            'slip'    => $slip,
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
