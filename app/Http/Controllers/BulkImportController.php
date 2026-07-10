<?php
namespace App\Http\Controllers;

use App\Models\CcpmManpower;
use App\Models\ActivityLog;
use App\Models\DriverDetail;
use App\Models\EmployeeTraining;
use App\Models\TrainingType;
use App\Models\Employee;
use App\Models\Equipment;
use App\Models\EquipmentOperator;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Carbon\Carbon;

class BulkImportController extends Controller
{
    // ── Helper: pesan error ramah user ───────────────────────
    private static function friendlyError(\Exception $e, string $context = ''): string
    {
        $msg = $e->getMessage();

        if (str_contains($msg, 'cannot be null')) {
            preg_match("/Column '(\w+)' cannot be null/", $msg, $m);
            $col = $m[1] ?? 'kolom wajib';
            $labels = [
                'id_badge'      => 'ID Badge',
                'nama_lengkap'  => 'Nama Lengkap',
                'no_ktp'        => 'No. KTP',
                'operator_name' => 'Nama Operator',
                'no_unit'       => 'No. Unit',
                'employee_id'   => 'Karyawan',
                'name'          => 'Nama',
            ];
            $label = $labels[$col] ?? $col;
            return ($context ? "$context — " : '') . "Kolom \"$label\" wajib diisi tapi kosong.";
        }

        if (str_contains($msg, 'Duplicate entry')) {
            preg_match("/Duplicate entry '(.+?)' for key/", $msg, $m);
            $val = $m[1] ?? '';
            return ($context ? "$context — " : '') . "Data \"$val\" sudah ada di sistem (duplikat).";
        }

        if (str_contains($msg, 'foreign key constraint')) {
            return ($context ? "$context — " : '') . "Data referensi tidak ditemukan di sistem.";
        }

        if (str_contains($msg, 'Data too long')) {
            preg_match("/column '(\w+)'/i", $msg, $m);
            $col = $m[1] ?? 'kolom';
            return ($context ? "$context — " : '') . "Nilai di kolom \"$col\" terlalu panjang.";
        }

        if (str_contains($msg, 'Incorrect date') || str_contains($msg, 'Incorrect datetime')) {
            return ($context ? "$context — " : '') . "Format tanggal tidak valid. Gunakan format DD-MM-YYYY.";
        }

        return ($context ? "$context — " : '') . "Gagal disimpan. Periksa kembali data di baris ini.";
    }

    // ── Helper parse tanggal ──────────────────────────────────
    private static function parseDate($val): ?string
    {
        if (empty($val) || in_array(trim((string) $val), ['-', '—', 'N/A', 'n/a', '']))
            return null;
        if (is_numeric($val)) {
            try {
                return ExcelDate::excelToDateTimeObject($val)->format('Y-m-d');
            } catch (\Exception $e) {
                return null;
            }
        }
        $val = trim($val);
        if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $val)) {
            try {
                return Carbon::createFromFormat('d-m-Y', $val)->format('Y-m-d');
            } catch (\Exception $e) {
                return null;
            }
        }
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $val)) {
            try {
                return Carbon::createFromFormat('d/m/Y', $val)->format('Y-m-d');
            } catch (\Exception $e) {
                return null;
            }
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $val))
            return $val;
        return null;
    }

    // ── Helper clean string ───────────────────────────────────
    private static function cleanStr($val): ?string
    {
        $val = trim((string) ($val ?? ''));
        return in_array(strtolower($val), ['-', '—', 'n/a', 'null', '']) ? null : ($val ?: null);
    }

    // ── Helper load sheet ─────────────────────────────────────
    private function loadSheet(Request $request, string $field = 'file', int $sheetIndex = 0)
    {
        $request->validate([
            $field => 'required|file|mimes:xlsx,xls|max:10240',
        ]);
        $spreadsheet = IOFactory::load($request->file($field)->getRealPath());
        return $spreadsheet->getSheet($sheetIndex)->toArray(null, true, true, true);
    }

    // ═══════════════════════════════════════════════════════════
    // 1. IMPORT CCPM
    // ═══════════════════════════════════════════════════════════
    public function importCcpm(Request $request)
    {
        if ($this->isViewer())
            return back()->with('error', 'Viewer tidak memiliki akses.');

        $rows = $this->loadSheet($request);
        $pid  = $this->activeProjectId() ?? auth()->user()->project_id;

        $colMap = [
            'badge'            => 'A',
            'id_card'          => 'B',
            'hes_passport'     => 'C',
            'name'             => 'D',
            'birth_place'      => 'E',
            'birth_date'       => 'F',
            'ffd_valid_date'   => 'G',
            'badge_valid_date' => 'H',
            'job_title'        => 'I',
            'team_assignment'  => 'J',
            'status'           => 'K',
            'status_medical'   => 'L',
        ];
        $dateCols = ['birth_date', 'ffd_valid_date', 'badge_valid_date'];
        $results  = ['imported' => 0, 'skipped' => 0, 'errors' => [], 'skipped_list' => [], 'name_warning' => []];
        $rowNum   = 4;

        foreach ($rows as $rowIndex => $row) {
            $rowNum++;
            if ($rowIndex < 5) continue;

            $name = self::cleanStr($row[$colMap['name']] ?? '');
            if (!$name) continue;

            $idCard      = self::cleanStr($row[$colMap['id_card']]      ?? null);
            $hesPassport = self::cleanStr($row[$colMap['hes_passport']] ?? null);
            $badge       = self::cleanStr($row[$colMap['badge']]        ?? null);

            if (!$idCard) {
                $results['errors'][] = "Baris {$rowNum}: {$name} — No. KTP wajib diisi.";
                continue;
            }

            $existsQuery = CcpmManpower::where('project_id', $pid);

            if ($hesPassport && (clone $existsQuery)->where('hes_passport', $hesPassport)->exists()) {
                $results['skipped']++;
                $results['skipped_list'][] = "{$name} (HES Passport: {$hesPassport} sudah ada)";
                continue;
            }
            if ($idCard && (clone $existsQuery)->where('id_card', $idCard)->exists()) {
                $results['skipped']++;
                $results['skipped_list'][] = "{$name} (No. KTP: {$idCard} sudah ada)";
                continue;
            }
            if ($badge && (clone $existsQuery)->where('badge', $badge)->exists()) {
                $results['skipped']++;
                $results['skipped_list'][] = "{$name} (Badge: {$badge} sudah ada)";
                continue;
            }

            if (!$hesPassport && !$badge)
                $results['name_warning'][] = "{$name} (tidak ada HES Passport/Badge — mungkin duplikat, cek manual)";

            $data = ['project_id' => $pid, 'name' => $name, 'id_card' => $idCard];
            foreach ($colMap as $field => $col) {
                if ($field === 'name') continue;
                $data[$field] = in_array($field, $dateCols)
                    ? self::parseDate($row[$col] ?? null)
                    : self::cleanStr($row[$col] ?? null);
            }

            try {
                CcpmManpower::create($data);
                $results['imported']++;
            } catch (\Exception $e) {
                $results['errors'][] = "Baris {$rowNum}: " . self::friendlyError($e, $name);
            }
        }

        if ($results['imported'] > 0) {
            ActivityLog::create([
                'user_id'     => auth()->id(),
                'action'      => 'import',
                'module'      => 'CCPM',
                'target_name' => "Import Excel",
                'description' => "Import CCPM: {$results['imported']} berhasil, {$results['skipped']} di-skip, " . count($results['errors']) . " error",
                'ip_address'  => request()->ip(),
            ]);
        }
        return redirect()->back()->with('import_result', $results);
    }

    // ═══════════════════════════════════════════════════════════
    // 2. IMPORT DRIVER
    // ═══════════════════════════════════════════════════════════
    public function importDriver(Request $request)
    {
        if ($this->isViewer())
            return back()->with('error', 'Viewer tidak memiliki akses.');

        $rows = $this->loadSheet($request, 'file', 0);
        $pid  = $this->activeProjectId() ?? auth()->user()->project_id;

        $colMap = [
            'name'                  => 'A',
            'id_card'               => 'B',
            'badge'                 => 'C',
            'license_type'          => 'D',
            'license_no'            => 'E',
            'rfid'                  => 'F',
            'posttest_schedule'     => 'G',
            'permit_expired_date'   => 'H',
            'driver_status'         => 'I',
            'posttest_status'       => 'J',
            'date_approve_posttest' => 'K',
            'dvp_status'            => 'L',
        ];
        $dateCols = ['posttest_schedule', 'permit_expired_date', 'date_approve_posttest'];
        $results  = ['imported' => 0, 'skipped' => 0, 'errors' => [], 'skipped_list' => []];
        $rowNum   = 4;

        foreach ($rows as $rowIndex => $row) {
            $rowNum++;
            if ($rowIndex < 5) continue;

            $name   = self::cleanStr($row[$colMap['name']]   ?? '');
            if (!$name) continue;

            $idCard = self::cleanStr($row[$colMap['id_card']] ?? null);
            $badge  = self::cleanStr($row[$colMap['badge']]   ?? null);
            $existsQuery = DriverDetail::where('project_id', $pid);

            if ($idCard) {
                if ((clone $existsQuery)->where('id_card', $idCard)->exists()) {
                    $results['skipped']++;
                    $results['skipped_list'][] = "{$name} (No. KTP: {$idCard} sudah ada)";
                    continue;
                }
            } elseif ($badge) {
                if ((clone $existsQuery)->where('badge', $badge)->exists()) {
                    $results['skipped']++;
                    $results['skipped_list'][] = "{$name} (Badge: {$badge} sudah ada)";
                    continue;
                }
            } else {
                if ((clone $existsQuery)->where('name', $name)->exists()) {
                    $results['skipped']++;
                    $results['skipped_list'][] = "{$name} (nama sudah ada)";
                    continue;
                }
            }

            $data = ['project_id' => $pid, 'name' => $name];
            foreach ($colMap as $field => $col) {
                if ($field === 'name') continue;
                $data[$field] = in_array($field, $dateCols)
                    ? self::parseDate($row[$col] ?? null)
                    : self::cleanStr($row[$col] ?? null);
            }

            try {
                DriverDetail::create($data);
                $results['imported']++;
            } catch (\Exception $e) {
                $results['errors'][] = "Baris {$rowNum}: " . self::friendlyError($e, $name);
            }
        }

        if ($results['imported'] > 0) {
            ActivityLog::create([
                'user_id'     => auth()->id(),
                'action'      => 'import',
                'module'      => 'Driver',
                'target_name' => "Import Excel",
                'description' => "Import Driver: {$results['imported']} berhasil, {$results['skipped']} di-skip, " . count($results['errors']) . " error",
                'ip_address'  => request()->ip(),
            ]);
        }
        return redirect()->back()->with('import_result', $results);
    }

    // ═══════════════════════════════════════════════════════════
    // 3. IMPORT TRAINING
    // ═══════════════════════════════════════════════════════════
    public function importTraining(Request $request)
    {
        if ($this->isViewer())
            return back()->with('error', 'Viewer tidak memiliki akses.');

        $rows  = $this->loadSheet($request);
        $pid   = $this->activeProjectId();
        $trainingTypes = TrainingType::pluck('id', 'nama');
        $results = ['imported' => 0, 'skipped' => 0, 'errors' => [], 'skipped_list' => []];
        $rowNum  = 4;

        foreach ($rows as $rowIndex => $row) {
            $rowNum++;
            if ($rowIndex < 5) continue;

            $idBadge   = self::cleanStr($row['A'] ?? '');
            $jenisNama = self::cleanStr($row['C'] ?? '');

            if (!$idBadge && !self::cleanStr($row['B'] ?? '')) continue;

            if (!$jenisNama) {
                $results['errors'][] = "Baris {$rowNum}: Jenis Training kosong.";
                continue;
            }

            $empQuery = Employee::when($pid, fn($q) => $q->where('project_id', $pid));
            if ($idBadge) {
                $emp = (clone $empQuery)->where('id_badge', $idBadge)->first();
                if (!$emp) $emp = (clone $empQuery)->where('no_ktp', $idBadge)->first();
            } else {
                $namaKar = self::cleanStr($row['B'] ?? '');
                $emp = (clone $empQuery)->where('nama_lengkap', 'like', "%{$namaKar}%")->first();
            }

            if (!$emp) {
                $identifier = $idBadge ?: self::cleanStr($row['B'] ?? '—');
                $results['errors'][] = "Baris {$rowNum}: Karyawan dengan NIK/Badge \"{$identifier}\" tidak ditemukan di sistem.";
                continue;
            }

            if (!$trainingTypes->has($jenisNama)) {
                $results['errors'][] = "Baris {$rowNum}: Jenis training \"{$jenisNama}\" tidak ditemukan di sistem. Pastikan nama training sama persis.";
                continue;
            }
            $trainingTypeId = $trainingTypes[$jenisNama];

            if (EmployeeTraining::where('employee_id', $emp->id)->where('training_type_id', $trainingTypeId)->exists()) {
                $results['skipped']++;
                $results['skipped_list'][] = "{$emp->nama_lengkap} — {$jenisNama} (sudah ada)";
                continue;
            }

            $data = [
                'employee_id'      => $emp->id,
                'training_type_id' => $trainingTypeId,
                'tanggal'          => self::parseDate($row['D'] ?? null),
                'nama_trainer'     => self::cleanStr($row['E'] ?? null),
                'nilai'            => self::cleanStr($row['F'] ?? null),
                'status'           => self::cleanStr($row['G'] ?? null),
                'catatan'          => self::cleanStr($row['H'] ?? null),
                'input_by'         => auth()->id(),
            ];

            try {
                EmployeeTraining::create($data);
                $results['imported']++;
            } catch (\Exception $e) {
                $results['errors'][] = "Baris {$rowNum}: " . self::friendlyError($e, $emp->nama_lengkap);
            }
        }

        if ($results['imported'] > 0) {
            ActivityLog::create([
                'user_id'     => auth()->id(),
                'action'      => 'import',
                'module'      => 'Training',
                'target_name' => "Import Excel",
                'description' => "Import Training: {$results['imported']} berhasil, {$results['skipped']} di-skip, " . count($results['errors']) . " error",
                'ip_address'  => request()->ip(),
            ]);
        }
        return redirect()->back()->with('import_result', $results);
    }

    // ═══════════════════════════════════════════════════════════
    // 4. IMPORT EQUIPMENT (Unit + Operator)
    // ═══════════════════════════════════════════════════════════
    public function importEquipmentFull(Request $request)
    {
        if ($this->isViewer())
            return back()->with('error', 'Viewer tidak memiliki akses.');

        $request->validate(['file' => 'required|file|mimes:xlsx,xls|max:10240']);

        $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
        $pid = $this->activeProjectId() ?? auth()->user()->project_id;

        $results = [
            'unit'     => ['imported' => 0, 'skipped' => 0, 'errors' => [], 'skipped_list' => []],
            'operator' => ['imported' => 0, 'skipped' => 0, 'errors' => [], 'skipped_list' => [], 'not_registered' => []],
        ];

        // ── SHEET 1: Equipment Unit ──────────────────────────────
        $sheet1 = $spreadsheet->getSheet(0)->toArray(null, true, true, true);

        $colMapUnit = [
            'no_unit'                  => 'A',
            'plat_nomor'               => 'B',
            'type_unit'                => 'C',
            'model'                    => 'D',
            'manufacture'              => 'E',
            'serial_no'                => 'F',
            'tahun'                    => 'G',
            'gps_unit_id'              => 'H',
            'kategori'                 => 'I',
            'kapasitas'                => 'J',
            'stnk_expired'             => 'K',
            'tax_expired'              => 'L',
            'kir_expired'              => 'M',
            'izin_non_bm_expired'      => 'N',
            'vehicle_pass_expired'     => 'O',
            'inspection_date'          => 'P',
            'smbr_pass_expired'        => 'Q',
            'green_stiker_expired'     => 'R',
            'sio_migas_no'             => 'S',
            'sio_migas_expired'        => 'T',
            'sio_disnaker_expired'     => 'U',
            'k3_p3a2_no'               => 'V',
            'k3_p3a2_expired'          => 'W',
            'tpe_cem_inspector'        => 'X',
            'contractor_cem_inspector' => 'Y',
            'location_of_inspection'   => 'Z',
            'status'                   => 'AA',
            'keterangan'               => 'AB',
        ];
        $dateColsUnit = [
            'stnk_expired','tax_expired','kir_expired','izin_non_bm_expired',
            'vehicle_pass_expired','inspection_date','smbr_pass_expired',
            'green_stiker_expired','sio_migas_expired','sio_disnaker_expired','k3_p3a2_expired',
        ];

        $rowNum = 4;
        foreach ($sheet1 as $rowIndex => $row) {
            $rowNum++;
            if ($rowIndex < 5) continue;

            $noUnit = self::cleanStr($row[$colMapUnit['no_unit']] ?? '');
            if (!$noUnit) continue;

            if (Equipment::whereRaw('LOWER(no_unit) = ?', [strtolower($noUnit)])->exists()) {
                $results['unit']['skipped']++;
                $results['unit']['skipped_list'][] = "{$noUnit} (sudah ada)";
                continue;
            }

            $data = ['project_id' => $pid, 'no_unit' => $noUnit];
            foreach ($colMapUnit as $field => $col) {
                if ($field === 'no_unit') continue;
                if (in_array($field, $dateColsUnit)) {
                    $data[$field] = self::parseDate($row[$col] ?? null);
                } elseif ($field === 'tahun') {
                    $val = self::cleanStr($row[$col] ?? null);
                    $data[$field] = $val && is_numeric($val) ? (int) $val : null;
                } else {
                    $data[$field] = self::cleanStr($row[$col] ?? null);
                }
            }

            try {
                Equipment::create($data);
                $results['unit']['imported']++;
            } catch (\Exception $e) {
                $results['unit']['errors'][] = "Baris {$rowNum}: " . self::friendlyError($e, $noUnit);
            }
        }

        // ── SHEET 2: Equipment Operator ──────────────────────────
        if ($spreadsheet->getSheetCount() < 2) {
            return redirect()->back()->with('import_result', [
                'unit'     => $results['unit'],
                'operator' => null,
            ]);
        }

        $sheet2 = $spreadsheet->getSheet(1)->toArray(null, true, true, true);

        $colMapOp = [
            'no_unit'              => 'A',
            'operator_name'        => 'B',
            'badge'                => 'C',
            'license_no'           => 'D',
            'license_expired_date' => 'E',
            'rfid'                 => 'F',
            'kp_no'                => 'G',
            'kp_expired_date'      => 'H',
            'cdrive_expired_date'  => 'I',
            'postest_expired_date' => 'J',
            'permit_no'            => 'K',
            'permit_expired_date'  => 'L',
            'sio_migas_no'         => 'M',
            'sio_migas_expired'    => 'N',
            'sio_disnaker_expired' => 'O',
            'k3_p3a2_no'           => 'P',
            'k3_p3a2_expired'      => 'Q',
        ];
        $dateColsOp = [
            'license_expired_date','kp_expired_date','cdrive_expired_date',
            'postest_expired_date','permit_expired_date','sio_migas_expired',
            'sio_disnaker_expired','k3_p3a2_expired',
        ];

        $rowNum = 4;
        foreach ($sheet2 as $rowIndex => $row) {
            $rowNum++;
            if ($rowIndex < 5) continue;

            $noUnit       = self::cleanStr($row[$colMapOp['no_unit']]       ?? '');
            $operatorName = self::cleanStr($row[$colMapOp['operator_name']] ?? '');
            if (!$operatorName) continue;

            $equipment = Equipment::whereRaw('LOWER(no_unit) = ?', [strtolower($noUnit)])
                ->where('project_id', $pid)
                ->first();

            if (!$equipment && $noUnit) {
                $results['operator']['errors'][] = "Baris {$rowNum}: Unit \"{$noUnit}\" tidak ditemukan di sistem. Pastikan unit sudah diimport terlebih dahulu.";
                continue;
            }

            // Coba link ke karyawan
            $employeeId = null;
            $badgeVal   = self::cleanStr($row[$colMapOp['badge']] ?? null);
            if ($badgeVal) {
                $emp = Employee::where('id_badge', $badgeVal)
                    ->when($pid, fn($q) => $q->where('project_id', $pid))
                    ->first();
                if ($emp) $employeeId = $emp->id;
            }
            if (!$employeeId) {
                $emp = Employee::whereRaw('LOWER(nama_lengkap) = ?', [strtolower($operatorName)])
                    ->when($pid, fn($q) => $q->where('project_id', $pid))
                    ->first();
                if ($emp) $employeeId = $emp->id;
            }
            if (!$employeeId)
                $results['operator']['not_registered'][] = $operatorName . ($badgeVal ? " (Badge: {$badgeVal})" : '');

            $data = [
                'project_id'    => $pid,
                'equipment_id'  => $equipment?->id,
                'operator_name' => $operatorName,
                'employee_id'   => $employeeId,
                'is_active'     => true,
            ];
            foreach ($colMapOp as $field => $col) {
                if (in_array($field, ['no_unit', 'operator_name'])) continue;
                $data[$field] = in_array($field, $dateColsOp)
                    ? self::parseDate($row[$col] ?? null)
                    : self::cleanStr($row[$col] ?? null);
            }

            try {
                if ($equipment)
                    $equipment->operators()->where('is_active', true)->update(['is_active' => false]);
                EquipmentOperator::create($data);
                $results['operator']['imported']++;
            } catch (\Exception $e) {
                $results['operator']['errors'][] = "Baris {$rowNum}: " . self::friendlyError($e, $operatorName);
            }
        }

        if ($results['unit']['imported'] > 0 || $results['operator']['imported'] > 0) {
            ActivityLog::create([
                'user_id'     => auth()->id(),
                'action'      => 'import',
                'module'      => 'Equipment',
                'target_name' => "Import Excel",
                'description' => "Import Equipment — Unit: {$results['unit']['imported']} berhasil, Operator: {$results['operator']['imported']} berhasil",
                'ip_address'  => request()->ip(),
            ]);
        }

        return redirect()->back()->with('import_result', [
            'unit'     => $results['unit'],
            'operator' => $results['operator'],
        ]);
    }
}