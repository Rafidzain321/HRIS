<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeTransferController;
use App\Http\Controllers\McuController;
use App\Http\Controllers\BadgeKpController;
use App\Http\Controllers\SimController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\CcpmController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\TimesheetController;
use App\Http\Controllers\PpeController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\EmployeeDocumentController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\TrainingController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\TimesheetMemberController;
use App\Http\Controllers\EmployeeSpController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\EmployeeImportController;
use App\Http\Controllers\SlipGajiExportController;
use App\Http\Controllers\BulkImportController;
use App\Http\Controllers\TttConfigController;
use App\Http\Controllers\OvertimeCustomController;

Route::middleware(['auth'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/employees/check-badge', [EmployeeController::class, 'checkBadge'])->name('employees.check-badge');
    Route::get('/employees/template-import', function () {
        $path = public_path('templates/TEMPLATE_IMPORT_KARYAWAN.xlsx');
        if (!file_exists($path)) {
            abort(404, 'Template tidak ditemukan.');
        }
        return response()->download($path, 'TEMPLATE_IMPORT_KARYAWAN_HRIS_AKM.xlsx');
    })->name('employees.template-import');

    // ── Employee Transfer / Pindah Project ──────────────────
    Route::get('/employees/transfers', [EmployeeTransferController::class, 'index'])->name('employees.transfers.index');
    Route::get('/employees/transfers/pending-count', [EmployeeTransferController::class, 'pendingCount'])->name('employees.transfers.pending-count');
    Route::post('/employees/transfers/{transfer}/approve', [EmployeeTransferController::class, 'approve'])->name('employees.transfers.approve');
    Route::post('/employees/transfers/{transfer}/reject', [EmployeeTransferController::class, 'reject'])->name('employees.transfers.reject');
    Route::post('/employees/{employee}/transfer-direct', [EmployeeTransferController::class, 'transferDirect'])->name('employees.transfer.direct');
    Route::post('/employees/{employee}/transfer-request', [EmployeeTransferController::class, 'requestTransfer'])->name('employees.transfer.request');

    Route::resource('employees', EmployeeController::class);

    Route::prefix('compliance')->name('compliance.')->group(function () {
        Route::get('/ppe', [PpeController::class, 'index'])->name('ppe');
        Route::put('/ppe/{employee}', [PpeController::class, 'update'])->name('ppe.update');

        Route::get('/mcu/notifications', [McuController::class, 'notifications'])->name('mcu.notifications');
        Route::get('/mcu', [McuController::class, 'index'])->name('mcu');
        Route::put('/mcu/{employee}', [McuController::class, 'update'])->name('mcu.update');

        Route::get('/badge', [BadgeKpController::class, 'index'])->name('badge');
        Route::put('/badge/{employee}', [BadgeKpController::class, 'update'])->name('badge.update');

        Route::get('/sim', [SimController::class, 'index'])->name('sim');
        Route::put('/sim/{employee}', [SimController::class, 'update'])->name('sim.update');
    });

    Route::get('/notifications/data', [NotificationController::class, 'data'])->name('notifications.data');

    Route::get('/ccpm', [CcpmController::class, 'index'])->name('ccpm');
    Route::post('/ccpm', [CcpmController::class, 'store'])->name('ccpm.store');
    Route::put('/ccpm/{ccpm}', [CcpmController::class, 'update'])->name('ccpm.update');
    Route::delete('/ccpm/{ccpm}', [CcpmController::class, 'destroy'])->name('ccpm.destroy');

    Route::get('/driver', [DriverController::class, 'index'])->name('driver');
    Route::post('/driver', [DriverController::class, 'store'])->name('driver.store');
    Route::put('/driver/{driver}', [DriverController::class, 'update'])->name('driver.update');
    Route::delete('/driver/{driver}', [DriverController::class, 'destroy'])->name('driver.destroy');

    Route::get('/equipment', [EquipmentController::class, 'index'])->name('equipment');
    Route::post('/equipment', [EquipmentController::class, 'store'])->name('equipment.store');
    Route::put('/equipment/{equipment}', [EquipmentController::class, 'update'])->name('equipment.update');
    Route::delete('/equipment/{equipment}', [EquipmentController::class, 'destroy'])->name('equipment.destroy');
    Route::post('/equipment/{equipment}/operator', [EquipmentController::class, 'storeOperator'])->name('equipment.operator.store');
    Route::post('/equipment/{equipment}/assign-operator', [EquipmentController::class, 'assignOperator']);
    Route::put('/equipment/{equipment}/operator/{operator}', [EquipmentController::class, 'updateOperator'])->name('equipment.operator.update');
    Route::delete('/equipment/{equipment}/operator/{operator}', [EquipmentController::class, 'destroyOperator'])->name('equipment.operator.destroy');

    Route::get('/timesheet', [TimesheetController::class, 'index'])->name('timesheet');
    Route::post('/timesheet/update', [TimesheetController::class, 'update'])->name('timesheet.update');
    Route::get('/timesheet/export', [TimesheetController::class, 'export'])->name('timesheet.export');
    Route::get('/timesheet/members', [TimesheetMemberController::class, 'index']);
    Route::post('/timesheet/members', [TimesheetMemberController::class, 'store']);
    Route::post('/timesheet/members/bulk', [TimesheetMemberController::class, 'storeBulk']);
    Route::post('/timesheet/members/import-default', [TimesheetMemberController::class, 'importDefault']);
    Route::put('/timesheet/members/{member}', [TimesheetMemberController::class, 'update']);
    Route::delete('/timesheet/members/{member}', [TimesheetMemberController::class, 'destroy']);
    Route::post('/timesheet/members/reorder', [TimesheetMemberController::class, 'reorder'])->name('timesheet.members.reorder');
    Route::get('/timesheet/slip-gaji', [PayrollController::class, 'slipGaji'])->name('timesheet.slip-gaji');
    Route::get('/timesheet/slip-gaji/export-excel', [PayrollController::class, 'exportSlipExcel']);
    Route::get('/timesheet/data-gaji', [PayrollController::class, 'dataGaji'])->name('timesheet.data-gaji');
    Route::get('/timesheet/data-gaji/export', [\App\Http\Controllers\PayrollExportController::class, 'dataGaji'])->name('timesheet.data-gaji.export');
    Route::post('/timesheet/payroll/simpan', [PayrollController::class, 'simpanDataGaji'])->name('payroll.simpan');
    Route::put('/timesheet/payroll/{employee}/manual', [PayrollController::class, 'updateManual'])->name('payroll.update-manual');

    Route::get('/export/karyawan', [ExportController::class, 'karyawan'])->name('export.karyawan');
    Route::get('/export/mcu', [ExportController::class, 'mcu']);
    Route::get('/export/badge', [ExportController::class, 'badge']);
    Route::get('/export/sim', [ExportController::class, 'sim']);
    Route::get('/export/siosim', [ExportController::class, 'siosim']);
    Route::get('/export/driver', [ExportController::class, 'driver']);
    Route::get('/export/equipment-unit', [ExportController::class, 'equipmentUnit']);
    Route::get('/export/equipment-operator', [ExportController::class, 'equipmentOperator']);
    Route::get('/export/ccpm', [ExportController::class, 'ccpm']);
    Route::get('/export/training', [ExportController::class, 'training']);
    Route::get('/export/ppe', [ExportController::class, 'ppe']);

    Route::post('/employees/{employee}/terminate', [EmployeeController::class, 'terminate'])->name('employees.terminate');
    Route::post('/employees/{employee}/reactivate', [EmployeeController::class, 'reactivate'])->name('employees.reactivate');
    Route::put('/employees/{employee}/terminate-update', [EmployeeController::class, 'terminateUpdate'])->name('employees.terminate-update');
    Route::post('/employees/sp', [EmployeeSpController::class, 'storeSp']);
    Route::delete('/employees/sp/{sp}', [EmployeeSpController::class, 'destroySp']);
    Route::get('/employees/{id}/history', [EmployeeSpController::class, 'getHistory']);

    Route::post('/pengaturan/holidays', [HolidayController::class, 'store'])->name('holidays.store');
    Route::delete('/pengaturan/holidays/{holiday}', [HolidayController::class, 'destroy'])->name('holidays.destroy');

    Route::get('/notifications', [NotificationController::class, 'page'])->name('notifications.page');
    Route::get('/pengaturan', [UserManagementController::class, 'index'])->name('pengaturan');
    Route::post('/pengaturan/users', [UserManagementController::class, 'store']);
    Route::put('/pengaturan/users/{user}', [UserManagementController::class, 'update']);
    Route::post('/pengaturan/users/{user}/toggle', [UserManagementController::class, 'toggleActive']);
    Route::post('/pengaturan/users/{user}/reset-password', [UserManagementController::class, 'resetPassword']);
    Route::post('/pengaturan/change-password', [UserManagementController::class, 'changePassword']);
    Route::post('/logout', [UserManagementController::class, 'logout'])->name('logout');

    Route::get('/employees/{employee}/documents', [EmployeeDocumentController::class, 'index']);
    Route::post('/employees/{employee}/documents', [EmployeeDocumentController::class, 'upload']);
    Route::get('/employees/documents/{document}/preview', [EmployeeDocumentController::class, 'preview']);
    Route::get('/employees/documents/{document}/download', [EmployeeDocumentController::class, 'download']);
    Route::delete('/employees/documents/{document}', [EmployeeDocumentController::class, 'destroy']);

    Route::post('/pengaturan/positions', [PositionController::class, 'store'])->name('positions.store');
    Route::put('/pengaturan/positions/{position}', [PositionController::class, 'update'])->name('positions.update');
    Route::delete('/pengaturan/positions/{position}', [PositionController::class, 'destroy'])->name('positions.destroy');

    Route::get('/training', [TrainingController::class, 'index'])->name('training');

    Route::get('/employees/{employee}/trainings', [TrainingController::class, 'forEmployee']);
    Route::post('/employees/{employee}/trainings', [TrainingController::class, 'store']);
    Route::put('/employees/trainings/{training}', [TrainingController::class, 'update']);
    Route::delete('/employees/trainings/{training}', [TrainingController::class, 'destroy']);
    Route::post('/employees/import', [EmployeeImportController::class, 'import'])->name('employees.import');

    Route::post('/ccpm/import', [BulkImportController::class, 'importCcpm']);
    Route::post('/driver/import', [BulkImportController::class, 'importDriver']);
    Route::post('/training/import', [BulkImportController::class, 'importTraining']);
    Route::post('/equipment/import', [BulkImportController::class, 'importEquipmentFull']);

    // ── Template download routes ──
    Route::get('/ccpm/template-import', fn() => response()->download(public_path('templates/TEMPLATE_IMPORT_CCPM.xlsx')));
    Route::get('/driver/template-import', fn() => response()->download(public_path('templates/TEMPLATE_IMPORT_DRIVER.xlsx')));
    Route::get('/training/template-import', fn() => response()->download(public_path('templates/TEMPLATE_IMPORT_TRAINING.xlsx')));
    Route::get('/equipment/template-import', fn() => response()->download(public_path('templates/TEMPLATE_IMPORT_EQUIPMENT.xlsx')));

    Route::get('/training/types', fn() => \App\Models\TrainingType::where('is_active', true)->orderBy('urutan')->get());

    Route::post('/pengaturan/training-types', [TrainingController::class, 'storeType']);
    Route::put('/pengaturan/training-types/{trainingType}', [TrainingController::class, 'updateType']);
    Route::delete('/pengaturan/training-types/{trainingType}', [TrainingController::class, 'destroyType']);

    Route::get('/ttt-config', [TttConfigController::class, 'index']);
    Route::post('/ttt-config', [TttConfigController::class, 'store']);
    Route::put('/ttt-config/{item}', [TttConfigController::class, 'update']);
    Route::delete('/ttt-config/{item}', [TttConfigController::class, 'destroy']);
    Route::post('/ttt-config/reorder', [TttConfigController::class, 'reorder']);

    Route::get('/slip-gaji/{payrollId}/print', [SlipGajiExportController::class, 'print']);
    Route::get('/slip-gaji/{payrollId}/export-excel', [SlipGajiExportController::class, 'exportExcel']);

    Route::get('/overtime-custom', [OvertimeCustomController::class, 'index']);
    Route::put('/overtime-custom/{employeeId}', [OvertimeCustomController::class, 'update']);

    Route::post('/switch-project', function (\Illuminate\Http\Request $request) {
        $kode = $request->get('project');
        $user = auth()->user();
        $projectIds = $user->project_ids ? json_decode($user->project_ids, true) : [$user->project_id];
        $proj = \App\Models\Project::where('kode', $kode)->first();
        if ($proj && (in_array($proj->id, $projectIds) || $user->hasRole('super-admin'))) {
            session(['active_project_kode' => $proj->id]);
        }
        return back();
    })->middleware('auth')->name('switch.project');
});

require __DIR__ . '/auth.php';