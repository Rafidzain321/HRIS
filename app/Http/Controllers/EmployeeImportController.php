<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ImportsExcel;
use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\EmployeeHoDetail;
use App\Models\Position;
use App\Models\Project;
use Illuminate\Http\Request;

// Import Data Karyawan dari template Excel. NIK yang sudah ada di kantor yang sama dianggap karyawan
// yang sama -> cuma kolom yang diisi yang di-update (bukan bikin baris baru).
class EmployeeImportController extends Controller
{
    use ImportsExcel;

    // Nilai yang dianggap "sengaja dikosongkan" saat re-import (bukan sekadar kolom yang tidak diisi).
    private const NULL_TOKENS = ['-', '—', 'n/a', 'null'];

    private const STATUS = ['AKTIF', 'NONAKTIF'];
    private const AGAMA  = ['Islam', 'Kristen Protestan', 'Kristen Katolik', 'Hindu', 'Buddha', 'Konghucu'];
    private const PTKP   = ['TK/0', 'TK/1', 'TK/2', 'TK/3', 'K/0', 'K/1', 'K/2', 'K/3'];
    private const UNIT_HO = ['HO-1', 'HO-2'];
    private const STATUS_KARYAWAN_HO = ['PKWT', 'PKWTT', 'PROBATION'];

    private const EMPTY_RESULT = ['imported' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => [], 'skipped_list' => []];

    // Template kantor lapangan (kolom H = umur, rumus — dilewati).
    private const COLMAP = [
        'nama_lengkap'        => 'A',  // wajib
        'no_ktp'              => 'B',  // wajib
        'id_badge'            => 'C',
        'nama_ibu'            => 'D',
        'no_telepon'          => 'E',
        'tempat_lahir'        => 'F',
        'tanggal_lahir'       => 'G',
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
        'expire_badge'        => 'S',
        'rfid'                => 'T',
        'status_kp'           => 'U',
        'exp_kp'              => 'V',
        'type_sim'            => 'W',
        'no_sim'              => 'X',
        'sim_kota_keluar'     => 'Y',
        'expired_sim'         => 'Z',
        'sio_k3'              => 'AA',
        'no_sio'              => 'AB',
        'expire_sio'          => 'AC',
        'tipe_sio'            => 'AD',
        'nama_perusahaan_sio' => 'AE',
        'tgl_mcu'             => 'AF',
        'exp_mcu'             => 'AG',
        'status_mcu'          => 'AH',
        'lokasi_mcu'          => 'AI',
        'derajat_kesehatan'   => 'AJ',
        'ukuran_baju'         => 'AK',
        'ukuran_sepatu'       => 'AL',
        'start_pkwt'          => 'AM',
        'end_pkwt'            => 'AN',
        'no_contract'         => 'AO',
        'no_bpjs'             => 'AP',
        'no_rekening'         => 'AQ',
    ];
    private const DATE_COLS = [
        'tanggal_lahir', 'tanggal_masuk', 'expire_badge', 'exp_kp', 'expired_sim',
        'expire_sio', 'tgl_mcu', 'exp_mcu', 'start_pkwt', 'end_pkwt',
    ];
    private const STRING_FIELDS = [
        'nama_ibu', 'no_telepon', 'tempat_lahir', 'kota_asal', 'alamat', 'agama', 'tamatan', 'ptkp', 'ccpm', 'group',
        'rfid', 'status_kp', 'type_sim', 'no_sim', 'sim_kota_keluar', 'sio_k3', 'no_sio', 'tipe_sio', 'nama_perusahaan_sio',
        'status_mcu', 'lokasi_mcu', 'derajat_kesehatan', 'ukuran_baju', 'ukuran_sepatu', 'no_contract', 'no_bpjs', 'no_rekening',
    ];

    // Template HO: tanpa SIM/SIO/MCU/Badge/PPE, ditambah kolom EmployeeHoDetail (disimpan ke employee_ho_details).
    private const HO_COLMAP = [
        'nama_lengkap'    => 'A',  // wajib
        'no_ktp'          => 'B',  // wajib
        'unit'            => 'C',
        'nik_ho'          => 'D',
        'no_telepon'      => 'E',
        'tempat_lahir'    => 'F',
        'tanggal_lahir'   => 'G',
        'tanggal_masuk'   => 'H',
        'jabatan'         => 'I',
        'alamat'          => 'J',
        'agama'           => 'K',
        'ptkp'            => 'L',
        'status'          => 'M',
        'status_karyawan' => 'N',
        'nama_ktp'        => 'O',
        'no_kk'           => 'P',
        'rt_rw'           => 'Q',
        'kelurahan'       => 'R',
        'kecamatan'       => 'S',
        'propinsi'        => 'T',
        'npwp'            => 'U',
        'email'           => 'V',
        'lokasi_kerja'    => 'W',
        'start_pkwt'      => 'X',
        'end_pkwt'        => 'Y',
        'no_contract'     => 'Z',
        'no_rekening'     => 'AA',
    ];
    private const HO_DATE_COLS     = ['tanggal_lahir', 'tanggal_masuk', 'start_pkwt', 'end_pkwt'];
    private const HO_STRING_FIELDS = ['no_telepon', 'tempat_lahir', 'alamat', 'agama', 'ptkp', 'no_contract', 'no_rekening'];
    private const HO_DETAIL_FIELDS = ['unit', 'nik_ho', 'status_karyawan', 'nama_ktp', 'no_kk', 'rt_rw', 'kelurahan', 'kecamatan', 'propinsi', 'npwp', 'email', 'lokasi_kerja'];

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls|max:10240']);

        $pid = $this->activeProjectId();
        if ($pid && Project::find($pid)?->tipe_gaji === 'ho') {
            return $this->importHo($request, $pid);
        }

        $rows      = $this->loadSheet($request, null);
        $positions = Position::pluck('id', 'nama_jabatan');
        $results   = self::EMPTY_RESULT;
        $user      = auth()->user();
        $projectId = $pid ?? ($user->hasRole('super-admin') ? null : $user->project_id);
        $col       = self::COLMAP;

        foreach ($this->dataRows($rows) as $rowNum => $row) {
            $nama       = trim($row[$col['nama_lengkap']] ?? '');
            $noKtp      = trim($row[$col['no_ktp']] ?? '');
            $idBadge    = trim($row[$col['id_badge']] ?? '');
            $statusRaw  = trim($row[$col['status']] ?? '');
            $statusNorm = strtoupper($statusRaw ?: 'AKTIF');

            if (empty($nama) && empty($noKtp) && empty($idBadge)) continue;
            if ($error = $this->wajibKosong($rowNum, $nama, $noKtp)) {
                $results['errors'][] = $error;
                continue;
            }

            $existing = Employee::where('no_ktp', $noKtp)
                ->when($projectId, fn($q) => $q->where('project_id', $projectId))
                ->first();

            // ID Badge yang sudah dipakai karyawan LAIN (bukan dirinya sendiri saat update) -> dilewati.
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

            $jabatan    = trim($row[$col['jabatan']] ?? '');
            $positionId = $this->resolvePosition($jabatan, $positions);

            if ($existing) {
                $updateData = $this->buildUpdateData($row, $col, self::STRING_FIELDS)
                    + $this->buildUpdateDateData($row, $col, self::DATE_COLS);
                if ($nama !== $existing->nama_lengkap) $updateData['nama_lengkap'] = $nama;
                if (!empty($idBadge)) $updateData['id_badge'] = $idBadge;
                if ($statusRaw !== '' && in_array($statusNorm, self::STATUS)) $updateData['status'] = $statusNorm;
                if ($jabatan) $updateData['position_id'] = $positionId;

                if (empty($updateData)) {
                    $this->tidakBerubah($results, $nama, $noKtp);
                    continue;
                }
                $this->simpan($results, 'updated', $rowNum, $nama, fn() => $existing->update($updateData));
                continue;
            }

            $data = [
                'nama_lengkap' => $nama,
                'no_ktp'       => $noKtp,
                'id_badge'     => $idBadge ?: null,
                'status'       => in_array($statusNorm, self::STATUS) ? $statusNorm : 'AKTIF',
                'position_id'  => $positionId,
                'project_id'   => $projectId,
            ];
            foreach (self::STRING_FIELDS as $field) $data[$field] = self::cleanStr($row[$col[$field]] ?? '');
            foreach (self::DATE_COLS as $field) $data[$field] = self::parseDate($row[$col[$field]] ?? null);

            $this->simpan($results, 'imported', $rowNum, $nama, fn() => Employee::create($data));
        }

        if ($results['imported'] > 0 || $results['updated'] > 0) {
            ActivityLog::record('import', 'Data Karyawan', 'Import Excel', "Import karyawan: {$results['imported']} baru, {$results['updated']} diupdate, {$results['skipped']} di-skip, " . count($results['errors']) . ' error');
        }

        return redirect()->back()->with('import_result', $results);
    }

    private function importHo(Request $request, int $pid)
    {
        $rows      = $this->loadSheet($request, null);
        $positions = Position::pluck('id', 'nama_jabatan');
        $results   = self::EMPTY_RESULT;
        $col       = self::HO_COLMAP;

        foreach ($this->dataRows($rows) as $rowNum => $row) {
            $nama       = trim($row[$col['nama_lengkap']] ?? '');
            $noKtp      = trim($row[$col['no_ktp']] ?? '');
            $statusRaw  = trim($row[$col['status']] ?? '');
            $statusNorm = strtoupper($statusRaw ?: 'AKTIF');

            if (empty($nama) && empty($noKtp)) continue;
            if ($error = $this->wajibKosong($rowNum, $nama, $noKtp)) {
                $results['errors'][] = $error;
                continue;
            }

            $existing   = Employee::where('no_ktp', $noKtp)->where('project_id', $pid)->first();
            $jabatan    = trim($row[$col['jabatan']] ?? '');
            $positionId = $this->resolvePosition($jabatan, $positions);

            if ($existing) {
                $updateData = $this->buildUpdateData($row, $col, self::HO_STRING_FIELDS)
                    + $this->buildUpdateDateData($row, $col, self::HO_DATE_COLS);
                if ($nama !== $existing->nama_lengkap) $updateData['nama_lengkap'] = $nama;
                if ($statusRaw !== '' && in_array($statusNorm, self::STATUS)) $updateData['status'] = $statusNorm;
                if ($jabatan) $updateData['position_id'] = $positionId;
                // Mode update: isi yang tidak valid dilewati (tidak menimpa data lama).
                $this->normalizeOrDrop($updateData, 'agama', self::AGAMA, true);
                $this->normalizeOrDrop($updateData, 'ptkp', self::PTKP);

                $hoUpdateData = $this->buildUpdateData($row, $col, self::HO_DETAIL_FIELDS);
                $this->normalizeOrDrop($hoUpdateData, 'unit', self::UNIT_HO);
                $this->normalizeOrDrop($hoUpdateData, 'status_karyawan', self::STATUS_KARYAWAN_HO);

                if (empty($updateData) && empty($hoUpdateData)) {
                    $this->tidakBerubah($results, $nama, $noKtp);
                    continue;
                }
                $this->simpan($results, 'updated', $rowNum, $nama, function () use ($existing, $updateData, $hoUpdateData) {
                    if (!empty($updateData)) $existing->update($updateData);
                    if (!empty($hoUpdateData)) EmployeeHoDetail::updateOrCreate(['employee_id' => $existing->id], $hoUpdateData);
                });
                continue;
            }

            $data = [
                'nama_lengkap' => $nama,
                'no_ktp'       => $noKtp,
                'status'       => in_array($statusNorm, self::STATUS) ? $statusNorm : 'AKTIF',
                'position_id'  => $positionId,
                'project_id'   => $pid,
            ];
            foreach (self::HO_STRING_FIELDS as $field) $data[$field] = self::cleanStr($row[$col[$field]] ?? '');
            // Mode baru: isi yang tidak valid jadi null.
            if (!empty($data['agama'])) $data['agama'] = $this->normalizeChoice($data['agama'], self::AGAMA, true)[1];
            if (!empty($data['ptkp']))  $data['ptkp']  = $this->normalizeChoice($data['ptkp'], self::PTKP)[1];
            foreach (self::HO_DATE_COLS as $field) $data[$field] = self::parseDate($row[$col[$field]] ?? null);

            $hoData = [];
            foreach (self::HO_DETAIL_FIELDS as $field) $hoData[$field] = self::cleanStr($row[$col[$field]] ?? '');
            if (!empty($hoData['unit']))            $hoData['unit']            = $this->normalizeChoice($hoData['unit'], self::UNIT_HO)[1];
            if (!empty($hoData['status_karyawan'])) $hoData['status_karyawan'] = $this->normalizeChoice($hoData['status_karyawan'], self::STATUS_KARYAWAN_HO)[1];

            $this->simpan($results, 'imported', $rowNum, $nama, function () use ($data, $hoData) {
                $employee = Employee::create($data);
                if (array_filter($hoData, fn($v) => $v !== null)) {
                    EmployeeHoDetail::updateOrCreate(['employee_id' => $employee->id], $hoData);
                }
            });
        }

        if ($results['imported'] > 0 || $results['updated'] > 0) {
            ActivityLog::record('import', 'Data Karyawan', 'Import Excel (HO)', "Import karyawan HO: {$results['imported']} baru, {$results['updated']} diupdate, {$results['skipped']} di-skip, " . count($results['errors']) . ' error');
        }

        return redirect()->back()->with('import_result', $results);
    }

    private function wajibKosong(int $rowNum, string $nama, string $noKtp): ?string
    {
        if (empty($nama))  return "Baris {$rowNum}: Nama Lengkap kosong.";
        if (empty($noKtp)) return "Baris {$rowNum}: No. KTP kosong — {$nama}";
        return null;
    }

    // Jabatan yang belum ada di master otomatis dibuat.
    private function resolvePosition(string $jabatan, $positions): ?int
    {
        if (!$jabatan) return null;
        if (!$positions->has($jabatan)) {
            $positions[$jabatan] = Position::create(['nama_jabatan' => $jabatan])->id;
        }
        return $positions[$jabatan];
    }

    private function tidakBerubah(array &$results, string $nama, string $noKtp): void
    {
        $results['skipped']++;
        $results['skipped_list'][] = "{$nama} (NIK: {$noKtp} — tidak ada perubahan)";
    }

    private function simpan(array &$results, string $counter, int $rowNum, string $nama, callable $fn): void
    {
        try {
            $fn();
            $results[$counter]++;
        } catch (\Exception $e) {
            $results['errors'][] = "Baris {$rowNum}: " . self::friendlyError($e, $nama, false);
        }
    }

    // Cocokkan dengan daftar pilihan resmi. Return [cocok?, nilai baku|null].
    private function normalizeChoice(?string $val, array $valid, bool $caseInsensitive = false): array
    {
        if ($val === null || $val === '') return [false, null];
        $match = $caseInsensitive
            ? collect($valid)->first(fn($o) => strcasecmp($o, $val) === 0)
            : (in_array(strtoupper($val), $valid) ? strtoupper($val) : null);
        return $match !== null ? [true, $match] : [false, null];
    }

    private function normalizeOrDrop(array &$data, string $field, array $valid, bool $caseInsensitive = false): void
    {
        if (!isset($data[$field])) return;
        [$ok, $val] = $this->normalizeChoice($data[$field], $valid, $caseInsensitive);
        if ($ok) $data[$field] = $val; else unset($data[$field]);
    }

    // Re-import: cuma kolom yang diisi di Excel yang di-update; sel kosong tidak menimpa data lama
    // (supaya import ulang untuk 1 perubahan kecil tidak menghapus data lain). "-"/"n/a"/dst = sengaja dikosongkan.
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

    // Sama untuk kolom tanggal: sel kosong atau tanggal yang gagal dibaca dilewati
    // (jangan sampai tanggal lama terhapus karena salah ketik format di baris re-import).
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
}
