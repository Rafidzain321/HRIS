<?php
namespace App\Imports;
// app>Imports>EmployeeImport.php
use App\Models\Employee;
use App\Models\Position;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Carbon\Carbon;

class EmployeeImport implements ToModel, WithHeadingRow, SkipsEmptyRows
{
    private $bar;
    private $positionCache = [];
    public int $imported = 0;
    public int $skipped  = 0;

    public function __construct($bar = null) { $this->bar = $bar; }

    public function headingRow(): int { return 3; }

    public function model(array $row): ?Employee
    {
        // ── Bersihkan dan validasi id_badge ──
        $idBadge = trim((string)($row['id_badge'] ?? ''));

        // Skip kalau kosong, ada formula Excel, atau terlalu panjang
        if (
            empty($idBadge) ||
            strlen($idBadge) > 30 ||
            str_contains($idBadge, '=') ||
            str_contains($idBadge, 'VLOOKUP') ||
            str_contains($idBadge, 'FALSE') ||
            !preg_match('/^[A-Za-z0-9\-]+$/', $idBadge)
        ) {
            $this->skipped++;
            return null;
        }

        // ── Bersihkan nama ──
        $nama = trim((string)($row['nama'] ?? ''));
        if (
            empty($nama) ||
            strlen($nama) > 200 ||
            str_contains($nama, '=') ||
            str_contains($nama, 'VLOOKUP')
        ) {
            $this->skipped++;
            return null;
        }

        // ── Resolve jabatan → position_id ──
        $positionId = $this->resolvePosition($this->cleanStr($row['jabatan'] ?? null));

        // ── Status: hanya terima AKTIF / NONAKTIF / RESIGN ──
        $statusRaw = strtoupper(trim((string)($row['status'] ?? '')));
        $status = in_array($statusRaw, ['AKTIF', 'NONAKTIF', 'RESIGN']) ? $statusRaw : 'AKTIF';

        // ── SIO K3: hanya YES / NO ──
        $sioK3 = strtoupper(trim((string)($row['sio_k3'] ?? 'NO')));
        $sioK3 = in_array($sioK3, ['YES', 'NO']) ? $sioK3 : 'NO';

        try {
            $emp = Employee::updateOrCreate(
                ['id_badge' => $idBadge],
                [
                    'nama_lengkap'        => $nama,
                    'position_id'         => $positionId,
                    'group'               => $this->cleanStr($row['group'] ?? 'AKM') ?? 'AKM',
                    'status'              => $status,
                    'no_telepon'          => $this->cleanStr($row['no_tlp'] ?? null, 25),
                    'tempat_lahir'        => $this->cleanStr($row['tempat_lahir'] ?? null, 100),
                    'tanggal_lahir'       => $this->parseDate($row['tanggal_lahir'] ?? null),
                    'alamat'              => $this->cleanStr($row['alamat'] ?? null),
                    'kota_asal'           => $this->cleanStr($row['kota_asal'] ?? null, 100),
                    'no_ktp'              => $this->cleanKtp($row['no_ktp'] ?? null),
                    'ccpm'                => $this->cleanStr($row['ccpm'] ?? null, 30),
                    'expire_badge'        => $this->parseDate($row['expire_badge'] ?? null),
                    'status_kp'           => $this->cleanStr($row['status_kp'] ?? null, 100),
                    'kp_ready'            => $this->cleanStr($row['kp_ready'] ?? null, 100),
                    'exp_kp'              => $this->parseDate($row['exp_kp'] ?? null),
                    'type_sim'            => $this->cleanStr($row['type_sim'] ?? null, 10),
                    'no_sim'              => $this->cleanStr($row['no_sim'] ?? null, 30),
                    'expired_sim'         => $this->parseDate($row['expired_sim'] ?? null),
                    'sio_k3'              => $sioK3,
                    'no_sio'              => $this->cleanStr($row['no_sio'] ?? null, 50),
                    'expire_sio'          => $this->parseDate($row['expire_sio'] ?? null),
                    'nama_perusahaan_sio' => $this->cleanStr($row['nama_perusahaan_sio'] ?? null, 200),
                    'tipe_sio'            => $this->cleanStr($row['tipe_sio'] ?? null, 100),
                    'tamatan'             => $this->cleanStr($row['tamatan'] ?? null, 20),
                    'exp_mcu'             => $this->parseDate($row['exp_mcu'] ?? null),
                    'status_mcu'          => $this->cleanStr($row['status_mcu'] ?? null, 20),
                    'lokasi_mcu'          => $this->cleanStr($row['lokasi_mcu'] ?? null, 100),
                    'ukuran_baju'         => $this->cleanStr($row['baju'] ?? null, 10),
                    'ukuran_sepatu'       => $this->cleanSepatu($row['sepatu'] ?? null),
                    'rfid'                => $this->cleanStr(isset($row['rfid']) ? (string)$row['rfid'] : null, 50),
                    'disnaker'            => $this->cleanStr($row['disnaker'] ?? null, 255),
                    'tanggal_hi'          => $this->parseDate($row['hi'] ?? null),
                    'nama_trainer_hi'     => $this->cleanStr($row['nama_trainer'] ?? null, 150),
                    'swp_pt_ha'           => $this->parseDate($row['swp_pt_ha'] ?? null),
                    'nama_trainer_swp'    => $this->cleanStr($row['nama_trainerb'] ?? null, 150),
                    'hasil_posttest_swp'  => $this->cleanFloat($row['hasil_posttest_swp_ptw_ha'] ?? null),
                    'status_posttest_pwtha' => $this->cleanStr($row['status_post_test_pwtha'] ?? null, 20),
                    'spotter_flagman'     => $this->cleanStr($row['spotter_dan_flagman'] ?? null, 10),
                ]
            );

            $this->imported++;
            $this->bar?->advance();
            return $emp;

        } catch (\Exception $e) {
            $this->skipped++;
            return null;
        }
    }

    // ── HELPERS ──────────────────────────────────────────────

    private function cleanStr($value, int $maxLen = 255): ?string
    {
        if ($value === null || $value === '') return null;
        $str = trim((string)$value);
        if (
            empty($str) ||
            str_contains($str, '=') ||
            str_contains($str, 'VLOOKUP') ||
            str_contains($str, 'FALSE') ||
            str_contains($str, 'DATA BASE') ||
            str_contains($str, 'DATA DRIVER')
        ) return null;
        return mb_substr($str, 0, $maxLen) ?: null;
    }

    private function cleanKtp($value): ?string
    {
        if (!$value) return null;
        $ktp = preg_replace('/[^0-9]/', '', (string)$value);
        return (strlen($ktp) >= 16 && strlen($ktp) <= 20) ? substr($ktp, 0, 20) : null;
    }

    private function cleanSepatu($value): ?string
    {
        if ($value === null || $value === '') return null;
        $str = trim((string)$value);
        // Kalau ada formula atau terlalu panjang, skip
        if (str_contains($str, '=') || strlen($str) > 10) return null;
        return $str;
    }

    private function cleanFloat($value): ?float
    {
        if ($value === null || $value === '') return null;
        $str = (string)$value;
        if (str_contains($str, '=') || str_contains($str, 'VLOOKUP')) return null;
        return is_numeric($str) ? (float)$str : null;
    }

    private function parseDate($value): ?string
    {
        if ($value === null || $value === '') return null;
        $str = (string)$value;

        // Skip formula Excel
        if (str_contains($str, '=') || str_contains($str, 'VLOOKUP')) return null;

        // Skip kalau pure angka (bisa serial date Excel, tapi rawan salah)
        if (is_numeric($value) && strlen((string)(int)$value) > 5) return null;

        try {
            $date = Carbon::parse($str);
            // Validasi tahun masuk akal (1950–2040)
            if ($date->year < 1950 || $date->year > 2040) return null;
            return $date->format('Y-m-d');
        } catch (\Exception) {
            return null;
        }
    }

    private function resolvePosition(?string $jabatan): ?int
    {
        if (!$jabatan) return null;
        $jabatan = trim($jabatan);
        if (empty($jabatan) || strlen($jabatan) > 150) return null;
        if (isset($this->positionCache[$jabatan])) return $this->positionCache[$jabatan];

        $pos = Position::firstOrCreate(
            ['nama_jabatan' => $jabatan],
            ['level' => 'staff']
        );
        $this->positionCache[$jabatan] = $pos->id;
        return $pos->id;
    }
}
