<?php
namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Position;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Carbon\Carbon;

class EmployeeImportController extends Controller
{
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

        $results = [
            'imported'     => 0,
            'skipped'      => 0,
            'errors'       => [],
            'skipped_list' => [],
        ];

        $pid = $this->activeProjectId();

        $rowNum = 4; // hitung mulai baris 5
        foreach ($rows as $rowIndex => $row) {
            $rowNum++;
            if ($rowIndex < 5) continue; // skip baris 1-4 (header)

            $nama    = trim($row[$colMap['nama_lengkap']] ?? '');
            $noKtp   = trim($row[$colMap['no_ktp']] ?? '');
            $idBadge = trim($row[$colMap['id_badge']] ?? '');
            $status  = strtoupper(trim($row[$colMap['status']] ?? 'AKTIF'));

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

            // Skip jika NIK sudah ada di project yang sama
            $nikExists = Employee::where('no_ktp', $noKtp)
                ->when($projectId, fn($q) => $q->where('project_id', $projectId))
                ->exists();
            if ($nikExists) {
                $results['skipped']++;
                $results['skipped_list'][] = "{$nama} (NIK: {$noKtp} sudah ada)";
                continue;
            }

            // Skip jika ID Badge sudah ada (hanya jika diisi)
            if (!empty($idBadge)) {
                $badgeExists = Employee::where('id_badge', $idBadge)
                    ->when($projectId, fn($q) => $q->where('project_id', $projectId))
                    ->exists();
                if ($badgeExists) {
                    $results['skipped']++;
                    $results['skipped_list'][] = "{$nama} (Badge: {$idBadge} sudah ada)";
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

            // Build data array
            $data = [
                'nama_lengkap' => $nama,
                'no_ktp'       => $noKtp,
                'id_badge'     => $idBadge ?: null,
                'status'       => in_array($status, ['AKTIF', 'NONAKTIF']) ? $status : 'AKTIF',
                'position_id'  => $positionId,
                'project_id'   => $projectId,
            ];

            // Field string biasa
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

            foreach ($stringFields as $field) {
                if (!isset($colMap[$field])) continue;
                $val        = trim($row[$colMap[$field]] ?? '');
                $data[$field] = in_array(strtolower($val), array_map('strtolower', $nullValues))
                    ? null
                    : ($val ?: null);
            }

            // Field tanggal
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