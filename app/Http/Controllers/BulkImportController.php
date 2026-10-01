<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ImportsExcel;
use App\Models\ActivityLog;
use App\Models\CcpmManpower;
use App\Models\DriverDetail;
use App\Models\Employee;
use App\Models\EmployeeTraining;
use App\Models\Equipment;
use App\Models\EquipmentOperator;
use App\Models\TrainingType;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;

// Import Excel modul compliance (CCPM, Driver, Training, Equipment). Data yang sudah ada dilewati, tidak di-update.
class BulkImportController extends Controller
{
    use ImportsExcel;

    private const EMPTY_RESULT = ['imported' => 0, 'skipped' => 0, 'errors' => [], 'skipped_list' => []];

    public function importCcpm(Request $request)
    {
        if ($this->isViewer()) return back()->with('error', 'Viewer tidak memiliki akses.');

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
        $results  = self::EMPTY_RESULT + ['name_warning' => []];

        foreach ($this->dataRows($rows) as $rowNum => $row) {
            $name = self::cleanStr($row[$colMap['name']] ?? '');
            if (!$name) continue;

            $idCard      = self::cleanStr($row[$colMap['id_card']] ?? null);
            $hesPassport = self::cleanStr($row[$colMap['hes_passport']] ?? null);
            $badge       = self::cleanStr($row[$colMap['badge']] ?? null);

            if (!$idCard) {
                $results['errors'][] = "Baris {$rowNum}: {$name} — No. KTP wajib diisi.";
                continue;
            }

            // Duplikat dicek berurutan: HES Passport, No. KTP, Badge (dalam kantor yang sama).
            $existsQuery = CcpmManpower::where('project_id', $pid);
            if ($hesPassport && (clone $existsQuery)->where('hes_passport', $hesPassport)->exists()) {
                $this->skip($results, "{$name} (HES Passport: {$hesPassport} sudah ada)");
                continue;
            }
            if ((clone $existsQuery)->where('id_card', $idCard)->exists()) {
                $this->skip($results, "{$name} (No. KTP: {$idCard} sudah ada)");
                continue;
            }
            if ($badge && (clone $existsQuery)->where('badge', $badge)->exists()) {
                $this->skip($results, "{$name} (Badge: {$badge} sudah ada)");
                continue;
            }

            if (!$hesPassport && !$badge) {
                $results['name_warning'][] = "{$name} (tidak ada HES Passport/Badge — mungkin duplikat, cek manual)";
            }

            $data = ['project_id' => $pid, 'name' => $name, 'id_card' => $idCard] + $this->rowData($row, $colMap, $dateCols, ['name']);
            $this->simpan($results, $rowNum, $name, fn() => CcpmManpower::create($data));
        }

        $this->logImport($results, 'CCPM');
        return redirect()->back()->with('import_result', $results);
    }

    public function importDriver(Request $request)
    {
        if ($this->isViewer()) return back()->with('error', 'Viewer tidak memiliki akses.');

        $rows = $this->loadSheet($request);
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
        $results  = self::EMPTY_RESULT;

        foreach ($this->dataRows($rows) as $rowNum => $row) {
            $name = self::cleanStr($row[$colMap['name']] ?? '');
            if (!$name) continue;

            // Duplikat dikenali dari No. KTP; kalau kosong dari Badge; kalau keduanya kosong dari nama.
            $idCard = self::cleanStr($row[$colMap['id_card']] ?? null);
            $badge  = self::cleanStr($row[$colMap['badge']] ?? null);
            [$field, $value, $label] = $idCard ? ['id_card', $idCard, "No. KTP: {$idCard}"]
                : ($badge ? ['badge', $badge, "Badge: {$badge}"] : ['name', $name, 'nama']);
            if (DriverDetail::where('project_id', $pid)->where($field, $value)->exists()) {
                $this->skip($results, "{$name} ({$label} sudah ada)");
                continue;
            }

            $data = ['project_id' => $pid, 'name' => $name] + $this->rowData($row, $colMap, $dateCols, ['name']);
            $this->simpan($results, $rowNum, $name, fn() => DriverDetail::create($data));
        }

        $this->logImport($results, 'Driver');
        return redirect()->back()->with('import_result', $results);
    }

    // Kolom: A = NIK/Badge, B = nama (dipakai kalau A kosong), C = jenis training, D-H = detail.
    public function importTraining(Request $request)
    {
        if ($this->isViewer()) return back()->with('error', 'Viewer tidak memiliki akses.');

        $rows          = $this->loadSheet($request);
        $pid           = $this->activeProjectId();
        $trainingTypes = TrainingType::pluck('id', 'nama');
        $results       = self::EMPTY_RESULT;

        foreach ($this->dataRows($rows) as $rowNum => $row) {
            $idBadge   = self::cleanStr($row['A'] ?? '');
            $namaKar   = self::cleanStr($row['B'] ?? '');
            $jenisNama = self::cleanStr($row['C'] ?? '');

            if (!$idBadge && !$namaKar) continue;
            if (!$jenisNama) {
                $results['errors'][] = "Baris {$rowNum}: Jenis Training kosong.";
                continue;
            }

            $empQuery = Employee::when($pid, fn($q) => $q->where('project_id', $pid));
            $emp = $idBadge
                ? ((clone $empQuery)->where('id_badge', $idBadge)->first() ?? (clone $empQuery)->where('no_ktp', $idBadge)->first())
                : (clone $empQuery)->where('nama_lengkap', 'like', "%{$namaKar}%")->first();

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
                $this->skip($results, "{$emp->nama_lengkap} — {$jenisNama} (sudah ada)");
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
            $this->simpan($results, $rowNum, $emp->nama_lengkap, fn() => EmployeeTraining::create($data));
        }

        $this->logImport($results, 'Training');
        return redirect()->back()->with('import_result', $results);
    }

    // Sheet 1 = unit, sheet 2 (opsional) = operator. Operator baru jadi operator aktif unitnya.
    public function importEquipmentFull(Request $request)
    {
        if ($this->isViewer()) return back()->with('error', 'Viewer tidak memiliki akses.');

        $request->validate(['file' => 'required|file|mimes:xlsx,xls|max:10240']);
        $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
        $pid = $this->activeProjectId() ?? auth()->user()->project_id;

        $results = [
            'unit'     => self::EMPTY_RESULT,
            'operator' => self::EMPTY_RESULT + ['not_registered' => []],
        ];

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
            'stnk_expired', 'tax_expired', 'kir_expired', 'izin_non_bm_expired', 'vehicle_pass_expired', 'inspection_date',
            'smbr_pass_expired', 'green_stiker_expired', 'sio_migas_expired', 'sio_disnaker_expired', 'k3_p3a2_expired',
        ];

        foreach ($this->dataRows($spreadsheet->getSheet(0)->toArray(null, true, true, true)) as $rowNum => $row) {
            $noUnit = self::cleanStr($row[$colMapUnit['no_unit']] ?? '');
            if (!$noUnit) continue;

            if (Equipment::whereRaw('LOWER(no_unit) = ?', [strtolower($noUnit)])->exists()) {
                $this->skip($results['unit'], "{$noUnit} (sudah ada)");
                continue;
            }

            $data = ['project_id' => $pid, 'no_unit' => $noUnit] + $this->rowData($row, $colMapUnit, $dateColsUnit, ['no_unit']);
            $tahun = self::cleanStr($row[$colMapUnit['tahun']] ?? null);
            $data['tahun'] = $tahun && is_numeric($tahun) ? (int) $tahun : null;

            $this->simpan($results['unit'], $rowNum, $noUnit, fn() => Equipment::create($data));
        }

        if ($spreadsheet->getSheetCount() < 2) {
            return redirect()->back()->with('import_result', ['unit' => $results['unit'], 'operator' => null]);
        }

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
            'license_expired_date', 'kp_expired_date', 'cdrive_expired_date', 'postest_expired_date',
            'permit_expired_date', 'sio_migas_expired', 'sio_disnaker_expired', 'k3_p3a2_expired',
        ];

        foreach ($this->dataRows($spreadsheet->getSheet(1)->toArray(null, true, true, true)) as $rowNum => $row) {
            $noUnit       = self::cleanStr($row[$colMapOp['no_unit']] ?? '');
            $operatorName = self::cleanStr($row[$colMapOp['operator_name']] ?? '');
            if (!$operatorName) continue;

            $equipment = Equipment::whereRaw('LOWER(no_unit) = ?', [strtolower((string) $noUnit)])->where('project_id', $pid)->first();
            if (!$equipment && $noUnit) {
                $results['operator']['errors'][] = "Baris {$rowNum}: Unit \"{$noUnit}\" tidak ditemukan di sistem. Pastikan unit sudah diimport terlebih dahulu.";
                continue;
            }

            // Hubungkan ke data karyawan: dari badge dulu, kalau tidak ketemu dari nama persis.
            $badgeVal = self::cleanStr($row[$colMapOp['badge']] ?? null);
            $empQuery = Employee::when($pid, fn($q) => $q->where('project_id', $pid));
            $employeeId = ($badgeVal ? (clone $empQuery)->where('id_badge', $badgeVal)->value('id') : null)
                ?? (clone $empQuery)->whereRaw('LOWER(nama_lengkap) = ?', [strtolower($operatorName)])->value('id');
            if (!$employeeId) {
                $results['operator']['not_registered'][] = $operatorName . ($badgeVal ? " (Badge: {$badgeVal})" : '');
            }

            $data = [
                'project_id'    => $pid,
                'equipment_id'  => $equipment?->id,
                'operator_name' => $operatorName,
                'employee_id'   => $employeeId,
                'is_active'     => true,
            ] + $this->rowData($row, $colMapOp, $dateColsOp, ['no_unit', 'operator_name']);

            $this->simpan($results['operator'], $rowNum, $operatorName, function () use ($equipment, $data) {
                $equipment?->operators()->where('is_active', true)->update(['is_active' => false]);
                EquipmentOperator::create($data);
            });
        }

        if ($results['unit']['imported'] > 0 || $results['operator']['imported'] > 0) {
            ActivityLog::record('import', 'Equipment', 'Import Excel', "Import Equipment — Unit: {$results['unit']['imported']} berhasil, Operator: {$results['operator']['imported']} berhasil");
        }

        return redirect()->back()->with('import_result', ['unit' => $results['unit'], 'operator' => $results['operator']]);
    }

    // Nilai kolom-kolom baris sesuai $colMap: tanggal di-parse, sisanya dibersihkan. Kolom di $except dilewati.
    private function rowData(array $row, array $colMap, array $dateCols, array $except): array
    {
        $data = [];
        foreach ($colMap as $field => $col) {
            if (in_array($field, $except)) continue;
            $data[$field] = in_array($field, $dateCols) ? self::parseDate($row[$col] ?? null) : self::cleanStr($row[$col] ?? null);
        }
        return $data;
    }

    private function skip(array &$results, string $alasan): void
    {
        $results['skipped']++;
        $results['skipped_list'][] = $alasan;
    }

    private function simpan(array &$results, int $rowNum, string $nama, callable $fn): void
    {
        try {
            $fn();
            $results['imported']++;
        } catch (\Exception $e) {
            $results['errors'][] = "Baris {$rowNum}: " . self::friendlyError($e, $nama);
        }
    }

    private function logImport(array $results, string $modul): void
    {
        if ($results['imported'] > 0) {
            ActivityLog::record('import', $modul, 'Import Excel', "Import {$modul}: {$results['imported']} berhasil, {$results['skipped']} di-skip, " . count($results['errors']) . ' error');
        }
    }
}
