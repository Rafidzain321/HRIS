<?php
namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\EmployeeHoDetail;
use App\Models\Position;
use App\Models\Project;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Carbon\Carbon;

class EmployeeImportController extends Controller
{
    // Nilai-nilai yang dianggap "sengaja dikosongkan" (bukan kolom yang cuma tidak ikut diisi ulang).
    private const NULL_TOKENS = ['-', '—', 'n/a', 'null'];

    // Dipakai waktu re-import (NIK sudah ada di sistem) — cuma kolom yang benar-benar diisi di
    // Excel yang ikut di-update, kolom yang dikosongkan di baris itu TIDAK menimpa data lama
    // (supaya import ulang buat 1 perubahan kecil tidak menghapus data lain yang sudah ada).
    // Isi eksplisit "-"/"n/a"/dst tetap dianggap niat mengosongkan field itu.
    private function buildUpdateData(array $row, array $colMap, array $fields): array
    {
        $data = [];
        foreach ($fields as $field) {
            if (!isset($colMap[$field])) continue;
            $raw = trim((string) ($row[$colMap[$field]] ?? ''));
            if ($raw === '') continue;
            $data[$field] = in_array(strtolower($raw), self::NULL_TOKENS) ? null : $raw;
        }
        return $data;
    }

    // Sama seperti buildUpdateData() tapi untuk kolom tanggal — sel kosong dilewati (tidak
    // menimpa), sel yang gagal di-parse juga dilewati (jangan sampai mengosongkan tanggal lama
    // cuma karena format di baris re-import salah ketik).
    private function buildUpdateDateData(array $row, array $colMap, array $dateFields): array
    {
        $data = [];
        foreach ($dateFields as $field) {
            if (!isset($colMap[$field])) continue;
            $raw = $row[$colMap[$field]] ?? null;
            if ($raw === null || trim((string) $raw) === '') continue;
            $parsed = self::parseDate($raw);
            if ($parsed !== null) $data[$field] = $parsed;
        }
        return $data;
    }

    private static function friendlyError(\Exception $e, string $context = ''): string
    {
        $msg = $e->getMessage();

        if (str_contains($msg, 'cannot be null')) {
            preg_match("/Column '(\w+)' cannot be null/", $msg, $m);
            $col = $m[1] ?? 'kolom wajib';
            $labels = [
                'id_badge'    => 'ID Badge',
                'nama_lengkap'=> 'Nama Lengkap',
                'no_ktp'      => 'No. KTP',
            ];
            $label = $labels[$col] ?? $col;
            return ($context ? "$context — " : '') . "Kolom \"$label\" wajib diisi tapi kosong.";
        }
        if (str_contains($msg, 'Duplicate entry')) {
            preg_match("/Duplicate entry '(.+?)' for key/", $msg, $m);
            $val = $m[1] ?? '';
            return ($context ? "$context — " : '') . "Data \"$val\" sudah ada di sistem (duplikat).";
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
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        $pid  = $this->activeProjectId();
        $isHo = $pid && Project::find($pid)?->tipe_gaji === 'ho';

        if ($isHo) {
            return $this->importHo($request, $pid);
        }

        $file        = $request->file('file');
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet       = $spreadsheet->getActiveSheet();
        $rows        = $sheet->toArray(null, true, true, true);

        // ── Kolom sesuai template baru (data mulai baris 6) ──
        // Baris 1: Judul, Baris 2: Petunjuk, Baris 3: Section,
        // Baris 4: field_name, Baris 5: Label+Keterangan, Baris 6+: Data
        $colMap = [
            'nama_lengkap'        => 'A',  // Wajib
            'no_ktp'              => 'B',  // Wajib
            'id_badge'            => 'C',  // Opsional
            'nama_ibu'            => 'D',
            'no_telepon'          => 'E',
            'tempat_lahir'        => 'F',
            'tanggal_lahir'       => 'G',
            // H = umur (formula, skip)
            'tanggal_masuk'       => 'I',
            'jabatan'             => 'J',
            'alamat'              => 'K',
            'kota_asal'           => 'L',
            'agama'               => 'M',
            'tamatan'             => 'N',
            'ptkp'                => 'O',
            'status'              => 'P',
            'ccpm'                => 'Q',
            'group'               => 'R',
            // BADGE & KP
            'expire_badge'        => 'S',
            'rfid'                => 'T',
            'status_kp'           => 'U',
            'exp_kp'              => 'V',
            // SIM
            'type_sim'            => 'W',
            'no_sim'              => 'X',
            'sim_kota_keluar'     => 'Y',
            'expired_sim'         => 'Z',
            // SIO K3
            'sio_k3'              => 'AA',
            'no_sio'              => 'AB',
            'expire_sio'          => 'AC',
            'tipe_sio'            => 'AD',
            'nama_perusahaan_sio' => 'AE',
            // MCU
            'tgl_mcu'             => 'AF',
            'exp_mcu'             => 'AG',
            'status_mcu'          => 'AH',
            'lokasi_mcu'          => 'AI',
            'derajat_kesehatan'   => 'AJ',
            // PPE
            'ukuran_baju'         => 'AK',
            'ukuran_sepatu'       => 'AL',
            // PKWT & KONTRAK
            'start_pkwt'          => 'AM',
            'end_pkwt'            => 'AN',
            'no_contract'         => 'AO',
            'no_bpjs'             => 'AP',
            // DATA GAJI
            'no_rekening'         => 'AQ',
        ];

        // Kolom tanggal yang perlu di-parse
        $dateCols = [
            'tanggal_lahir', 'tanggal_masuk', 'expire_badge', 'exp_kp',
            'expired_sim', 'expire_sio', 'tgl_mcu', 'exp_mcu',
            'start_pkwt', 'end_pkwt',
        ];

        // Cache semua jabatan
        $positions = Position::pluck('id', 'nama_jabatan');

        // Field string biasa (dipakai baik waktu bikin baru maupun waktu update data lama)
        $stringFields = [
            'nama_ibu', 'no_telepon', 'tempat_lahir', 'kota_asal',
            'alamat', 'agama', 'tamatan', 'ptkp', 'ccpm', 'group',
            'rfid', 'status_kp', 'type_sim', 'no_sim', 'sim_kota_keluar',
            'sio_k3', 'no_sio', 'tipe_sio', 'nama_perusahaan_sio',
            'status_mcu', 'lokasi_mcu', 'derajat_kesehatan',
            'ukuran_baju', 'ukuran_sepatu', 'no_contract', 'no_bpjs',
            'no_rekening',
        ];
        $nullValues = ['-', '—', 'n/a', 'null', 'NULL', ''];

        $results = [
            'imported'     => 0,
            'updated'      => 0,
            'skipped'      => 0,
            'errors'       => [],
            'skipped_list' => [],
        ];

        $pid = $this->activeProjectId();

        $rowNum = 4; // hitung mulai baris 5
        foreach ($rows as $rowIndex => $row) {
            $rowNum++;
            if ($rowIndex < 5) continue; // skip baris 1-4 (header)

            $nama       = trim($row[$colMap['nama_lengkap']] ?? '');
            $noKtp      = trim($row[$colMap['no_ktp']] ?? '');
            $idBadge    = trim($row[$colMap['id_badge']] ?? '');
            $statusRaw  = trim($row[$colMap['status']] ?? '');
            $statusNorm = strtoupper($statusRaw ?: 'AKTIF');

            // Skip baris kosong
            if (empty($nama) && empty($noKtp) && empty($idBadge)) continue;

            // Validasi wajib
            if (empty($nama)) {
                $results['errors'][] = "Baris {$rowNum}: Nama Lengkap kosong.";
                continue;
            }
            if (empty($noKtp)) {
                $results['errors'][] = "Baris {$rowNum}: No. KTP kosong — {$nama}";
                continue;
            }

            // Tentukan project_id
            $user = auth()->user();
            $projectId = $pid ?? ($user->hasRole('super-admin') ? null : $user->project_id);

            // NIK yang sama di project yang sama = karyawan yang sama → update data yang berubah
            // saja (bukan bikin baris baru, bukan di-skip begitu saja).
            $existing = Employee::where('no_ktp', $noKtp)
                ->when($projectId, fn($q) => $q->where('project_id', $projectId))
                ->first();

            // Skip kalau ID Badge sudah dipakai karyawan LAIN (bukan dirinya sendiri kalau ini update)
            if (!empty($idBadge)) {
                $badgeOwner = Employee::where('id_badge', $idBadge)
                    ->when($projectId, fn($q) => $q->where('project_id', $projectId))
                    ->first();
                if ($badgeOwner && (!$existing || $badgeOwner->id !== $existing->id)) {
                    $results['skipped']++;
                    $results['skipped_list'][] = "{$nama} (Badge: {$idBadge} sudah dipakai karyawan lain)";
                    continue;
                }
            }

            // Parse jabatan → position_id
            $jabatan    = trim($row[$colMap['jabatan']] ?? '');
            $positionId = null;
            if ($jabatan) {
                if ($positions->has($jabatan)) {
                    $positionId = $positions[$jabatan];
                } else {
                    // Buat jabatan baru kalau belum ada
                    $pos                 = Position::create(['nama_jabatan' => $jabatan]);
                    $positions[$jabatan] = $pos->id;
                    $positionId          = $pos->id;
                }
            }

            if ($existing) {
                // ── UPDATE: cuma kolom yang benar-benar diisi di baris ini yang ikut ditimpa ──
                $updateData = $this->buildUpdateData($row, $colMap, $stringFields);
                $updateData += $this->buildUpdateDateData($row, $colMap, $dateCols);
                if ($nama !== $existing->nama_lengkap) $updateData['nama_lengkap'] = $nama;
                if (!empty($idBadge)) $updateData['id_badge'] = $idBadge;
                if ($statusRaw !== '' && in_array($statusNorm, ['AKTIF', 'NONAKTIF'])) $updateData['status'] = $statusNorm;
                if ($jabatan) $updateData['position_id'] = $positionId;

                if (empty($updateData)) {
                    $results['skipped']++;
                    $results['skipped_list'][] = "{$nama} (NIK: {$noKtp} — tidak ada perubahan)";
                    continue;
                }

                try {
                    $existing->update($updateData);
                    $results['updated']++;
                } catch (\Exception $e) {
                    $results['errors'][] = "Baris {$rowNum}: " . self::friendlyError($e, $nama);
                }
                continue;
            }

            // ── BARU: karyawan dengan NIK ini belum ada, buat baris baru ──
            $data = [
                'nama_lengkap' => $nama,
                'no_ktp'       => $noKtp,
                'id_badge'     => $idBadge ?: null,
                'status'       => in_array($statusNorm, ['AKTIF', 'NONAKTIF']) ? $statusNorm : 'AKTIF',
                'position_id'  => $positionId,
                'project_id'   => $projectId,
            ];

            foreach ($stringFields as $field) {
                if (!isset($colMap[$field])) continue;
                $val        = trim($row[$colMap[$field]] ?? '');
                $data[$field] = in_array(strtolower($val), array_map('strtolower', $nullValues))
                    ? null
                    : ($val ?: null);
            }

            foreach ($dateCols as $field) {
                if (!isset($colMap[$field])) continue;
                $val        = $row[$colMap[$field]] ?? null;
                $data[$field] = self::parseDate($val);
            }

            try {
                Employee::create($data);
                $results['imported']++;
            } catch (\Exception $e) {
                $results['errors'][] = "Baris {$rowNum}: " . self::friendlyError($e, $nama);
            }
        }

        if ($results['imported'] > 0 || $results['updated'] > 0) {
            ActivityLog::record('import', 'Data Karyawan', 'Import Excel', "Import karyawan: {$results['imported']} baru, {$results['updated']} diupdate, {$results['skipped']} di-skip, " . count($results['errors']) . ' error');
        }

        return redirect()->back()->with('import_result', $results);
    }

    // ── Import Data Karyawan khusus HO — kolomnya beda dari project lapangan:
    // tidak ada SIM/SIO/MCU/Badge/PPE (tidak relevan untuk staf kantor pusat),
    // diganti kolom EmployeeHoDetail (unit, NIK HO, alamat KTP, dsb) yang ikut
    // disimpan ke tabel employee_ho_details, bukan cuma tabel employees.
    private function importHo(Request $request, int $pid)
    {
        $file        = $request->file('file');
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet       = $spreadsheet->getActiveSheet();
        $rows        = $sheet->toArray(null, true, true, true);

        $colMap = [
            'nama_lengkap'     => 'A',  // Wajib
            'no_ktp'           => 'B',  // Wajib
            'unit'             => 'C',  // HO-1 / HO-2
            'nik_ho'           => 'D',
            'no_telepon'       => 'E',
            'tempat_lahir'     => 'F',
            'tanggal_lahir'    => 'G',
            'tanggal_masuk'    => 'H',
            'jabatan'          => 'I',
            'alamat'           => 'J',
            'agama'            => 'K',
            'ptkp'             => 'L',
            'status'           => 'M',
            'status_karyawan'  => 'N',
            'nama_ktp'         => 'O',
            'no_kk'            => 'P',
            'rt_rw'            => 'Q',
            'kelurahan'        => 'R',
            'kecamatan'        => 'S',
            'propinsi'         => 'T',
            'npwp'             => 'U',
            'email'            => 'V',
            'lokasi_kerja'     => 'W',
            'start_pkwt'       => 'X',
            'end_pkwt'         => 'Y',
            'no_contract'      => 'Z',
            'no_rekening'      => 'AA',
        ];

        $dateCols = ['tanggal_lahir', 'tanggal_masuk', 'start_pkwt', 'end_pkwt'];
        $stringFields = ['no_telepon', 'tempat_lahir', 'alamat', 'agama', 'ptkp', 'no_contract', 'no_rekening'];
        $hoFields = [
            'unit', 'nik_ho', 'status_karyawan', 'nama_ktp', 'no_kk', 'rt_rw',
            'kelurahan', 'kecamatan', 'propinsi', 'npwp', 'email', 'lokasi_kerja',
        ];
        $nullValues = ['-', '—', 'n/a', 'null', 'NULL', ''];

        $positions = Position::pluck('id', 'nama_jabatan');

        $results = [
            'imported'     => 0,
            'updated'      => 0,
            'skipped'      => 0,
            'errors'       => [],
            'skipped_list' => [],
        ];

        // Agama/PTKP/Unit/Status Karyawan cuma boleh salah satu dari daftar resmi — kalau isi
        // Excel-nya tidak cocok, jangan sampai dipakai (biar tidak ada data "nyasar" yang tidak
        // terbaca fitur lain). $forCreate=true → nilai tidak valid diganti null (perilaku lama).
        // $forCreate=false (mode update) → nilai tidak valid cuma dilewati, tidak menimpa data lama.
        $normalizeChoice = function (?string $val, array $valid, bool $caseInsensitive = false) {
            if ($val === null || $val === '') return [false, null];
            $match = $caseInsensitive
                ? collect($valid)->first(fn ($o) => strcasecmp($o, $val) === 0)
                : (in_array(strtoupper($val), $valid) ? strtoupper($val) : null);
            return $match !== null ? [true, $match] : [false, null];
        };

        $rowNum = 4;
        foreach ($rows as $rowIndex => $row) {
            $rowNum++;
            if ($rowIndex < 5) continue;

            $nama       = trim($row[$colMap['nama_lengkap']] ?? '');
            $noKtp      = trim($row[$colMap['no_ktp']] ?? '');
            $statusRaw  = trim($row[$colMap['status']] ?? '');
            $statusNorm = strtoupper($statusRaw ?: 'AKTIF');

            if (empty($nama) && empty($noKtp)) continue;

            if (empty($nama)) {
                $results['errors'][] = "Baris {$rowNum}: Nama Lengkap kosong.";
                continue;
            }
            if (empty($noKtp)) {
                $results['errors'][] = "Baris {$rowNum}: No. KTP kosong — {$nama}";
                continue;
            }

            // NIK yang sama = karyawan yang sama → update kolom yang berubah saja.
            $existing = Employee::where('no_ktp', $noKtp)->where('project_id', $pid)->first();

            $jabatan    = trim($row[$colMap['jabatan']] ?? '');
            $positionId = null;
            if ($jabatan) {
                if ($positions->has($jabatan)) {
                    $positionId = $positions[$jabatan];
                } else {
                    $pos                 = Position::create(['nama_jabatan' => $jabatan]);
                    $positions[$jabatan] = $pos->id;
                    $positionId          = $pos->id;
                }
            }

            if ($existing) {
                // ── UPDATE: cuma kolom yang benar-benar diisi di baris ini yang ikut ditimpa ──
                $updateData = $this->buildUpdateData($row, $colMap, $stringFields);
                $updateData += $this->buildUpdateDateData($row, $colMap, $dateCols);
                if ($nama !== $existing->nama_lengkap) $updateData['nama_lengkap'] = $nama;
                if ($statusRaw !== '' && in_array($statusNorm, ['AKTIF', 'NONAKTIF'])) $updateData['status'] = $statusNorm;
                if ($jabatan) $updateData['position_id'] = $positionId;

                if (isset($updateData['agama'])) {
                    [$ok, $val] = $normalizeChoice($updateData['agama'], ['Islam', 'Kristen Protestan', 'Kristen Katolik', 'Hindu', 'Buddha', 'Konghucu'], true);
                    if ($ok) $updateData['agama'] = $val; else unset($updateData['agama']);
                }
                if (isset($updateData['ptkp'])) {
                    [$ok, $val] = $normalizeChoice($updateData['ptkp'], ['TK/0', 'TK/1', 'TK/2', 'TK/3', 'K/0', 'K/1', 'K/2', 'K/3']);
                    if ($ok) $updateData['ptkp'] = $val; else unset($updateData['ptkp']);
                }

                $hoUpdateData = $this->buildUpdateData($row, $colMap, $hoFields);
                if (isset($hoUpdateData['unit'])) {
                    [$ok, $val] = $normalizeChoice($hoUpdateData['unit'], ['HO-1', 'HO-2']);
                    if ($ok) $hoUpdateData['unit'] = $val; else unset($hoUpdateData['unit']);
                }
                if (isset($hoUpdateData['status_karyawan'])) {
                    [$ok, $val] = $normalizeChoice($hoUpdateData['status_karyawan'], ['PKWT', 'PKWTT']);
                    if ($ok) $hoUpdateData['status_karyawan'] = $val; else unset($hoUpdateData['status_karyawan']);
                }

                if (empty($updateData) && empty($hoUpdateData)) {
                    $results['skipped']++;
                    $results['skipped_list'][] = "{$nama} (NIK: {$noKtp} — tidak ada perubahan)";
                    continue;
                }

                try {
                    if (!empty($updateData)) $existing->update($updateData);
                    if (!empty($hoUpdateData)) EmployeeHoDetail::updateOrCreate(['employee_id' => $existing->id], $hoUpdateData);
                    $results['updated']++;
                } catch (\Exception $e) {
                    $results['errors'][] = "Baris {$rowNum}: " . self::friendlyError($e, $nama);
                }
                continue;
            }

            // ── BARU: karyawan dengan NIK ini belum ada, buat baris baru ──
            $data = [
                'nama_lengkap' => $nama,
                'no_ktp'       => $noKtp,
                'status'       => in_array($statusNorm, ['AKTIF', 'NONAKTIF']) ? $statusNorm : 'AKTIF',
                'position_id'  => $positionId,
                'project_id'   => $pid,
            ];

            foreach ($stringFields as $field) {
                $val          = trim($row[$colMap[$field]] ?? '');
                $data[$field] = in_array(strtolower($val), array_map('strtolower', $nullValues))
                    ? null
                    : ($val ?: null);
            }

            if (!empty($data['agama'])) {
                [, $val] = $normalizeChoice($data['agama'], ['Islam', 'Kristen Protestan', 'Kristen Katolik', 'Hindu', 'Buddha', 'Konghucu'], true);
                $data['agama'] = $val;
            }
            if (!empty($data['ptkp'])) {
                [, $val] = $normalizeChoice($data['ptkp'], ['TK/0', 'TK/1', 'TK/2', 'TK/3', 'K/0', 'K/1', 'K/2', 'K/3']);
                $data['ptkp'] = $val;
            }

            foreach ($dateCols as $field) {
                $val          = $row[$colMap[$field]] ?? null;
                $data[$field] = self::parseDate($val);
            }

            $hoData = [];
            foreach ($hoFields as $field) {
                $val = trim($row[$colMap[$field]] ?? '');
                $hoData[$field] = in_array(strtolower($val), array_map('strtolower', $nullValues))
                    ? null
                    : ($val ?: null);
            }
            if (!empty($hoData['unit'])) {
                [, $val] = $normalizeChoice($hoData['unit'], ['HO-1', 'HO-2']);
                $hoData['unit'] = $val;
            }
            if (!empty($hoData['status_karyawan'])) {
                [, $val] = $normalizeChoice($hoData['status_karyawan'], ['PKWT', 'PKWTT']);
                $hoData['status_karyawan'] = $val;
            }

            try {
                $employee = Employee::create($data);
                if (array_filter($hoData, fn ($v) => $v !== null)) {
                    EmployeeHoDetail::updateOrCreate(['employee_id' => $employee->id], $hoData);
                }
                $results['imported']++;
            } catch (\Exception $e) {
                $results['errors'][] = "Baris {$rowNum}: " . self::friendlyError($e, $nama);
            }
        }

        if ($results['imported'] > 0 || $results['updated'] > 0) {
            ActivityLog::record('import', 'Data Karyawan', 'Import Excel (HO)', "Import karyawan HO: {$results['imported']} baru, {$results['updated']} diupdate, {$results['skipped']} di-skip, " . count($results['errors']) . ' error');
        }

        return redirect()->back()->with('import_result', $results);
    }

    private static function parseDate($val): ?string
    {
        if (empty($val) || $val === '-' || $val === '—') return null;

        // Kalau angka = serial number Excel
        if (is_numeric($val)) {
            try {
                return ExcelDate::excelToDateTimeObject($val)->format('Y-m-d');
            } catch (\Exception $e) {
                return null;
            }
        }

        $val = trim($val);

        // Format DD-MM-YYYY
        if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $val)) {
            try {
                return Carbon::createFromFormat('d-m-Y', $val)->format('Y-m-d');
            } catch (\Exception $e) {
                return null;
            }
        }

        // Format YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
            return $val;
        }

        // Format DD/MM/YYYY
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $val)) {
            try {
                return Carbon::createFromFormat('d/m/Y', $val)->format('Y-m-d');
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }
}