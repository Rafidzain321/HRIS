<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeKpiController;
use App\Http\Controllers\ProjectController;
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
use App\Http\Controllers\PotonganConfigController;
use App\Http\Controllers\BpjsConfigController;
use App\Http\Controllers\TtdConfigController;
use App\Http\Controllers\OvertimeCustomController;

Route::middleware(['auth'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->middleware('menu:dashboard,view')->name('dashboard');
    Route::get('/employees/check-badge', [EmployeeController::class, 'checkBadge'])->middleware('menu:karyawan,view')->name('employees.check-badge');
    Route::get('/employees/template-import', function (\Illuminate\Http\Request $request) {
        $user = $request->user();
        if ($user->hasRole('super-admin') || $user->hasRole('viewer')) {
            $pid = session('active_project_kode') ?: null;
        } else {
            $projectIds = $user->project_ids ? json_decode($user->project_ids, true) : null;
            if ($projectIds && count($projectIds) > 1) {
                $sessionPid = session('active_project_kode');
                $pid = ($sessionPid && in_array($sessionPid, $projectIds)) ? $sessionPid : $projectIds[0];
            } else {
                $pid = $user->project_id;
            }
        }
        $isHo = $pid && \App\Models\Project::find($pid)?->tipe_gaji === 'ho';

        $path = $isHo
            ? public_path('templates/TEMPLATE_IMPORT_KARYAWAN_HO.xlsx')
            : public_path('templates/TEMPLATE_IMPORT_KARYAWAN.xlsx');
        if (!file_exists($path)) {
            abort(404, 'Template tidak ditemukan.');
        }
        $filename = $isHo ? 'TEMPLATE_IMPORT_KARYAWAN_HO_HRIS_AKM.xlsx' : 'TEMPLATE_IMPORT_KARYAWAN_HRIS_AKM.xlsx';
        return response()->download($path, $filename, [
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    })->middleware('menu:karyawan,view')->name('employees.template-import');

    // ── Employee Transfer / Pindah Project ──────────────────
    Route::get('/employees/transfers', [EmployeeTransferController::class, 'index'])->middleware('menu:karyawan,view')->name('employees.transfers.index');
    Route::get('/employees/transfers/pending-count', [EmployeeTransferController::class, 'pendingCount'])->middleware('menu:karyawan,view')->name('employees.transfers.pending-count');
    Route::post('/employees/transfers/{transfer}/approve', [EmployeeTransferController::class, 'approve'])->middleware('menu:karyawan,edit')->name('employees.transfers.approve');
    Route::post('/employees/transfers/{transfer}/reject', [EmployeeTransferController::class, 'reject'])->middleware('menu:karyawan,edit')->name('employees.transfers.reject');
    Route::post('/employees/{employee}/transfer-direct', [EmployeeTransferController::class, 'transferDirect'])->middleware('menu:karyawan,edit')->name('employees.transfer.direct');
    Route::post('/employees/{employee}/transfer-request', [EmployeeTransferController::class, 'requestTransfer'])->middleware('menu:karyawan,edit')->name('employees.transfer.request');
    Route::post('/employees/transfer-bulk', [EmployeeTransferController::class, 'transferBulk'])->middleware('menu:karyawan,edit')->name('employees.transfer.bulk');
    Route::post('/employees/{employee}/pindah-unit-ho', [EmployeeController::class, 'pindahUnitHo'])->middleware('menu:karyawan,edit')->name('employees.pindah-unit-ho');

    Route::resource('employees', EmployeeController::class)->only(['index', 'show'])->middleware('menu:karyawan,view');
    Route::resource('employees', EmployeeController::class)->only(['create', 'store', 'edit', 'update', 'destroy'])->middleware('menu:karyawan,edit');

    Route::prefix('compliance')->name('compliance.')->group(function () {
        Route::get('/ppe', [PpeController::class, 'index'])->middleware('menu:ppe,view')->name('ppe');
        Route::put('/ppe/{employee}', [PpeController::class, 'update'])->middleware('menu:ppe,edit')->name('ppe.update');

        Route::get('/mcu/notifications', [McuController::class, 'notifications'])->middleware('menu:mcu,view')->name('mcu.notifications');
        Route::get('/mcu', [McuController::class, 'index'])->middleware('menu:mcu,view')->name('mcu');
        Route::put('/mcu/{employee}', [McuController::class, 'update'])->middleware('menu:mcu,edit')->name('mcu.update');

        Route::get('/badge', [BadgeKpController::class, 'index'])->middleware('menu:badge,view')->name('badge');
        Route::put('/badge/{employee}', [BadgeKpController::class, 'update'])->middleware('menu:badge,edit')->name('badge.update');

        Route::get('/sim', [SimController::class, 'index'])->middleware('menu:sim,view')->name('sim');
        Route::put('/sim/{employee}', [SimController::class, 'update'])->middleware('menu:sim,edit')->name('sim.update');
    });

    Route::get('/notifications/data', [NotificationController::class, 'data'])->middleware('menu:notifications,view')->name('notifications.data');

    Route::get('/ccpm', [CcpmController::class, 'index'])->middleware('menu:ccpm,view')->name('ccpm');
    Route::post('/ccpm', [CcpmController::class, 'store'])->middleware('menu:ccpm,edit')->name('ccpm.store');
    Route::put('/ccpm/{ccpm}', [CcpmController::class, 'update'])->middleware('menu:ccpm,edit')->name('ccpm.update');
    Route::delete('/ccpm/{ccpm}', [CcpmController::class, 'destroy'])->middleware('menu:ccpm,edit')->name('ccpm.destroy');

    Route::get('/driver', [DriverController::class, 'index'])->middleware('menu:driver,view')->name('driver');
    Route::post('/driver', [DriverController::class, 'store'])->middleware('menu:driver,edit')->name('driver.store');
    Route::put('/driver/{driver}', [DriverController::class, 'update'])->middleware('menu:driver,edit')->name('driver.update');
    Route::delete('/driver/{driver}', [DriverController::class, 'destroy'])->middleware('menu:driver,edit')->name('driver.destroy');

    Route::get('/equipment', [EquipmentController::class, 'index'])->middleware('menu:equipment,view')->name('equipment');
    Route::post('/equipment', [EquipmentController::class, 'store'])->middleware('menu:equipment,edit')->name('equipment.store');
    Route::put('/equipment/{equipment}', [EquipmentController::class, 'update'])->middleware('menu:equipment,edit')->name('equipment.update');
    Route::delete('/equipment/{equipment}', [EquipmentController::class, 'destroy'])->middleware('menu:equipment,edit')->name('equipment.destroy');
    Route::post('/equipment/{equipment}/operator', [EquipmentController::class, 'storeOperator'])->middleware('menu:equipment,edit')->name('equipment.operator.store');
    Route::post('/equipment/{equipment}/assign-operator', [EquipmentController::class, 'assignOperator'])->middleware('menu:equipment,edit');
    Route::put('/equipment/{equipment}/operator/{operator}', [EquipmentController::class, 'updateOperator'])->middleware('menu:equipment,edit')->name('equipment.operator.update');
    Route::delete('/equipment/{equipment}/operator/{operator}', [EquipmentController::class, 'destroyOperator'])->middleware('menu:equipment,edit')->name('equipment.operator.destroy');

    Route::get('/timesheet', [TimesheetController::class, 'index'])->middleware('menu:timesheet,view')->name('timesheet');
    Route::post('/timesheet/update', [TimesheetController::class, 'update'])->middleware('menu:timesheet,edit')->name('timesheet.update');
    Route::get('/timesheet/export', [TimesheetController::class, 'export'])->middleware('menu:timesheet,view')->name('timesheet.export');
    Route::get('/timesheet/members', [TimesheetMemberController::class, 'index'])->middleware('menu:timesheet,view');
    Route::post('/timesheet/members', [TimesheetMemberController::class, 'store'])->middleware('menu:timesheet,edit');
    Route::post('/timesheet/members/bulk', [TimesheetMemberController::class, 'storeBulk'])->middleware('menu:timesheet,edit');
    Route::post('/timesheet/members/import-default', [TimesheetMemberController::class, 'importDefault'])->middleware('menu:timesheet,edit');
    Route::put('/timesheet/members/{member}', [TimesheetMemberController::class, 'update'])->middleware('menu:timesheet,edit');
    Route::delete('/timesheet/members/{member}', [TimesheetMemberController::class, 'destroy'])->middleware('menu:timesheet,edit');
    Route::post('/timesheet/members/reorder', [TimesheetMemberController::class, 'reorder'])->middleware('menu:timesheet,edit')->name('timesheet.members.reorder');
    Route::middleware(['payroll.access'])->group(function () {
        Route::get('/timesheet/slip-gaji', [PayrollController::class, 'slipGaji'])->middleware('menu:slip-gaji,view')->name('timesheet.slip-gaji');
        Route::get('/timesheet/slip-gaji/export-excel', [PayrollController::class, 'exportSlipExcel'])->middleware('menu:slip-gaji,view');
        Route::get('/timesheet/data-gaji', [PayrollController::class, 'dataGaji'])->middleware('menu:data-gaji,view')->name('timesheet.data-gaji');
        Route::get('/timesheet/data-gaji/export', [\App\Http\Controllers\PayrollExportController::class, 'dataGaji'])->middleware('menu:data-gaji,view')->name('timesheet.data-gaji.export');
        Route::put('/timesheet/payroll/{employee}/manual', [PayrollController::class, 'updateManual'])->middleware('menu:data-gaji,edit')->name('payroll.update-manual');
        Route::put('/timesheet/payroll/{employee}/info', [PayrollController::class, 'updateEmployeeInfo'])->middleware('menu:data-gaji,edit')->name('payroll.update-info');
        Route::put('/timesheet/payroll/{employee}/salary-history', [PayrollController::class, 'updateSalaryHistory'])->middleware('menu:data-gaji,edit')->name('payroll.update-salary-history');
    });

    Route::get('/export/karyawan', [ExportController::class, 'karyawan'])->middleware('menu:karyawan,view')->name('export.karyawan');
    Route::get('/export/mcu', [ExportController::class, 'mcu'])->middleware('menu:mcu,view');
    Route::get('/export/badge', [ExportController::class, 'badge'])->middleware('menu:badge,view');
    Route::get('/export/sim', [ExportController::class, 'sim'])->middleware('menu:sim,view');
    Route::get('/export/siosim', [ExportController::class, 'siosim'])->middleware('menu:sim,view');
    Route::get('/export/driver', [ExportController::class, 'driver'])->middleware('menu:driver,view');
    Route::get('/export/equipment-unit', [ExportController::class, 'equipmentUnit'])->middleware('menu:equipment,view');
    Route::get('/export/equipment-operator', [ExportController::class, 'equipmentOperator'])->middleware('menu:equipment,view');
    Route::get('/export/ccpm', [ExportController::class, 'ccpm'])->middleware('menu:ccpm,view');
    Route::get('/export/training', [ExportController::class, 'training'])->middleware('menu:training,view');
    Route::get('/export/ppe', [ExportController::class, 'ppe'])->middleware('menu:ppe,view');

    Route::post('/employees/{employee}/terminate', [EmployeeController::class, 'terminate'])->middleware('menu:karyawan,edit')->name('employees.terminate');
    Route::post('/employees/{employee}/reactivate', [EmployeeController::class, 'reactivate'])->middleware('menu:karyawan,edit')->name('employees.reactivate');
    Route::put('/employees/{employee}/terminate-update', [EmployeeController::class, 'terminateUpdate'])->middleware('menu:karyawan,edit')->name('employees.terminate-update');
    Route::post('/employees/sp', [EmployeeSpController::class, 'storeSp'])->middleware('menu:karyawan,edit');
    Route::delete('/employees/sp/{sp}', [EmployeeSpController::class, 'destroySp'])->middleware('menu:karyawan,edit');
    Route::get('/employees/{id}/history', [EmployeeSpController::class, 'getHistory'])->middleware('menu:karyawan,view');

    Route::post('/pengaturan/holidays', [HolidayController::class, 'store'])->name('holidays.store');
    Route::delete('/pengaturan/holidays/{holiday}', [HolidayController::class, 'destroy'])->name('holidays.destroy');

    Route::get('/notifications', [NotificationController::class, 'page'])->middleware('menu:notifications,view')->name('notifications.page');
    Route::get('/pengaturan', [UserManagementController::class, 'index'])->name('pengaturan');
    Route::post('/pengaturan/users', [UserManagementController::class, 'store']);
    Route::put('/pengaturan/users/{user}', [UserManagementController::class, 'update']);
    Route::post('/pengaturan/users/{user}/toggle', [UserManagementController::class, 'toggleActive']);
    Route::post('/pengaturan/users/{user}/reset-password', [UserManagementController::class, 'resetPassword']);
    Route::post('/pengaturan/change-password', [UserManagementController::class, 'changePassword']);
    Route::post('/logout', [UserManagementController::class, 'logout'])->name('logout');

    Route::get('/employees/{employee}/documents', [EmployeeDocumentController::class, 'index'])->middleware('menu:karyawan,view');
    Route::post('/employees/{employee}/documents', [EmployeeDocumentController::class, 'upload'])->middleware('menu:karyawan,edit');
    Route::get('/employees/documents/{document}/preview', [EmployeeDocumentController::class, 'preview'])->middleware('menu:karyawan,view');
    Route::get('/employees/documents/{document}/download', [EmployeeDocumentController::class, 'download'])->middleware('menu:karyawan,view');
    Route::delete('/employees/documents/{document}', [EmployeeDocumentController::class, 'destroy'])->middleware('menu:karyawan,edit');

    Route::post('/pengaturan/positions', [PositionController::class, 'store'])->name('positions.store');
    Route::put('/pengaturan/positions/{position}', [PositionController::class, 'update'])->name('positions.update');
    Route::delete('/pengaturan/positions/{position}', [PositionController::class, 'destroy'])->name('positions.destroy');

    Route::post('/pengaturan/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::put('/pengaturan/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::post('/pengaturan/projects/{project}/toggle', [ProjectController::class, 'toggleActive'])->name('projects.toggle');

    Route::get('/training', [TrainingController::class, 'index'])->middleware('menu:training,view')->name('training');

    // ── KPI (khusus karyawan Head Office) ──────────────────
    Route::get('/kpi', [EmployeeKpiController::class, 'index'])->middleware('menu:kpi,view')->name('kpi');
    Route::get('/kpi/summary', [EmployeeKpiController::class, 'summary'])->middleware('menu:kpi,view')->name('kpi.summary');
    Route::post('/kpi/indicators', [EmployeeKpiController::class, 'storeIndicator'])->middleware('menu:kpi,edit')->name('kpi.indicators.store');
    Route::put('/kpi/indicators/{indicator}', [EmployeeKpiController::class, 'updateIndicator'])->middleware('menu:kpi,edit')->name('kpi.indicators.update');
    Route::delete('/kpi/indicators/{indicator}', [EmployeeKpiController::class, 'destroyIndicator'])->middleware('menu:kpi,edit')->name('kpi.indicators.destroy');
    Route::post('/kpi/scores', [EmployeeKpiController::class, 'saveScore'])->middleware('menu:kpi,edit')->name('kpi.scores.save');

    Route::get('/employees/{employee}/trainings', [TrainingController::class, 'forEmployee'])->middleware('menu:training,view');
    Route::post('/employees/{employee}/trainings', [TrainingController::class, 'store'])->middleware('menu:training,edit');
    Route::put('/employees/trainings/{training}', [TrainingController::class, 'update'])->middleware('menu:training,edit');
    Route::delete('/employees/trainings/{training}', [TrainingController::class, 'destroy'])->middleware('menu:training,edit');
    Route::post('/employees/import', [EmployeeImportController::class, 'import'])->middleware('menu:karyawan,edit')->name('employees.import');

    Route::post('/ccpm/import', [BulkImportController::class, 'importCcpm'])->middleware('menu:ccpm,edit');
    Route::post('/driver/import', [BulkImportController::class, 'importDriver'])->middleware('menu:driver,edit');
    Route::post('/training/import', [BulkImportController::class, 'importTraining'])->middleware('menu:training,edit');
    Route::post('/equipment/import', [BulkImportController::class, 'importEquipmentFull'])->middleware('menu:equipment,edit');

    // ── Template download routes ──
    Route::get('/ccpm/template-import', fn() => response()->download(public_path('templates/TEMPLATE_IMPORT_CCPM.xlsx')))->middleware('menu:ccpm,view');
    Route::get('/driver/template-import', fn() => response()->download(public_path('templates/TEMPLATE_IMPORT_DRIVER.xlsx')))->middleware('menu:driver,view');
    Route::get('/training/template-import', fn() => response()->download(public_path('templates/TEMPLATE_IMPORT_TRAINING.xlsx')))->middleware('menu:training,view');
    Route::get('/equipment/template-import', fn() => response()->download(public_path('templates/TEMPLATE_IMPORT_EQUIPMENT.xlsx')))->middleware('menu:equipment,view');

    Route::get('/training/types', fn() => \App\Models\TrainingType::where('is_active', true)->orderBy('urutan')->get())->middleware('menu:training,view');

    Route::post('/pengaturan/training-types', [TrainingController::class, 'storeType'])->middleware('menu:training,edit');
    Route::put('/pengaturan/training-types/{trainingType}', [TrainingController::class, 'updateType'])->middleware('menu:training,edit');
    Route::delete('/pengaturan/training-types/{trainingType}', [TrainingController::class, 'destroyType'])->middleware('menu:training,edit');

    Route::get('/ttt-config', [TttConfigController::class, 'index'])->middleware('menu:data-gaji,view');
    Route::post('/ttt-config', [TttConfigController::class, 'store'])->middleware('menu:data-gaji,edit');
    Route::put('/ttt-config/{item}', [TttConfigController::class, 'update'])->middleware('menu:data-gaji,edit');
    Route::delete('/ttt-config/{item}', [TttConfigController::class, 'destroy'])->middleware('menu:data-gaji,edit');
    Route::post('/ttt-config/reorder', [TttConfigController::class, 'reorder'])->middleware('menu:data-gaji,edit');

    Route::get('/potongan-config', [PotonganConfigController::class, 'index'])->middleware('menu:data-gaji,view');
    Route::post('/potongan-config', [PotonganConfigController::class, 'store'])->middleware('menu:data-gaji,edit');
    Route::put('/potongan-config/{item}', [PotonganConfigController::class, 'update'])->middleware('menu:data-gaji,edit');
    Route::delete('/potongan-config/{item}', [PotonganConfigController::class, 'destroy'])->middleware('menu:data-gaji,edit');
    Route::post('/potongan-config/reorder', [PotonganConfigController::class, 'reorder'])->middleware('menu:data-gaji,edit');

    Route::get('/bpjs-config', [BpjsConfigController::class, 'index'])->middleware('menu:data-gaji,view');
    Route::post('/bpjs-config', [BpjsConfigController::class, 'store'])->middleware('menu:data-gaji,edit');
    Route::delete('/bpjs-config/{item}', [BpjsConfigController::class, 'destroy'])->middleware('menu:data-gaji,edit');

    Route::get('/ttd-config', [TtdConfigController::class, 'index'])->middleware('menu:data-gaji,view');
    Route::post('/ttd-config', [TtdConfigController::class, 'store'])->middleware('menu:data-gaji,edit');
    Route::delete('/ttd-config/{item}', [TtdConfigController::class, 'destroy'])->middleware('menu:data-gaji,edit');

    Route::middleware(['payroll.access'])->group(function () {
        Route::get('/slip-gaji/{payrollId}/print', [SlipGajiExportController::class, 'print'])->middleware('menu:slip-gaji,view');
        Route::get('/slip-gaji/{payrollId}/export-excel', [SlipGajiExportController::class, 'exportExcel'])->middleware('menu:slip-gaji,view');
    });

    Route::get('/overtime-custom', [OvertimeCustomController::class, 'index'])->middleware('menu:data-gaji,view');
    Route::put('/overtime-custom/{employeeId}', [OvertimeCustomController::class, 'update'])->middleware('menu:data-gaji,edit');

    Route::post('/switch-project', function (\Illuminate\Http\Request $request) {
        $kode = $request->get('project');
        $user = auth()->user();

        if ($kode === 'all' && ($user->hasRole('super-admin') || $user->hasRole('viewer'))) {
            session()->forget('active_project_kode');
            return back();
        }

        $projectIds = $user->project_ids ? json_decode($user->project_ids, true) : [$user->project_id];
        $proj = \App\Models\Project::where('kode', $kode)->first();
        if ($proj && (in_array($proj->id, $projectIds) || $user->hasRole('super-admin') || $user->hasRole('viewer'))) {
            session(['active_project_kode' => $proj->id]);
        }

        $refPath = parse_url($request->headers->get('referer') ?? '', PHP_URL_PATH) ?? '';

        // Kalau halaman yang sedang dibuka terikat ke satu karyawan spesifik (form Edit,
        // form Tambah, atau halaman detail karyawan), jangan kembali ke situ setelah pindah
        // project — karyawan itu belum tentu ada di project yang baru dipilih. Berlaku untuk
        // pindah ke project manapun, bukan cuma HO. Arahkan ke daftar Data Karyawan saja.
        if (preg_match('#^/employees/(create|\d+(/edit)?)$#', $refPath)) {
            return redirect('/employees');
        }

        // Kalau pindah ke HO dan halaman yang sedang dibuka adalah menu yang memang
        // tidak ada untuk HO (CCPM, Driver, Equipment, dll — lihat HO_HIDDEN_KEYS di
        // AppLayout.jsx), jangan kembali ke halaman itu (datanya pasti kosong dan
        // menunya sendiri sudah hilang dari sidebar) — arahkan ke Dashboard saja.
        if ($proj && strtoupper($proj->kode) === 'HO') {
            $hoHiddenPrefixes = ['/compliance/sim', '/compliance/mcu', '/compliance/badge', '/compliance/ppe', '/ccpm', '/driver', '/equipment', '/training'];
            $isHidden = collect($hoHiddenPrefixes)->contains(fn ($p) => str_starts_with($refPath, $p))
                || (str_starts_with($refPath, '/timesheet') && !str_starts_with($refPath, '/timesheet/slip-gaji') && !str_starts_with($refPath, '/timesheet/data-gaji'));
            if ($isHidden) {
                return redirect('/');
            }
        }

        return back();
    })->middleware('auth')->name('switch.project');
});

require __DIR__ . '/auth.php';
