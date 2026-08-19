<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\EmployeeHoDetail;
use App\Models\EmployeePayroll;
use App\Models\EmployeeSalaryHistory;
use App\Models\Position;
use App\Models\Project;
use Illuminate\Console\Command;

class ImportKaryawanHoFull extends Command
{
    protected $signature   = 'import:karyawan-ho-full {unit=all : HO-1, HO-2, atau all}';
    protected $description = 'Import jabatan + gaji terkini + riwayat gaji karyawan HO dari file karyawan-ho{1,2}-full.json';

    // label bulan (Indonesia, termasuk ejaan lama) -> nomor bulan.
    private const BULAN_MAP = [
        'JANUARI' => 1, 'JAN' => 1,
        'FEBRUARI' => 2, 'PEBRUARI' => 2, 'PEB' => 2, 'FEB' => 2,
        'MARET' => 3, 'MAR' => 3,
        'APRIL' => 4, 'APR' => 4,
        'MEI' => 5, 'MAY' => 5,
        'JUNI' => 6, 'JUN' => 6,
        'JULI' => 7, 'JUL' => 7,
        'AGUSTUS' => 8, 'AGST' => 8, 'AGSTS' => 8, 'AGT' => 8, 'AGU' => 8,
        'SEPTEMBER' => 9, 'SEPT' => 9, 'SEP' => 9,
        'OKTOBER' => 10, 'OKT' => 10, 'OCT' => 10,
        'NOPEMBER' => 11, 'NOVEMBER' => 11, 'NOP' => 11, 'NOV' => 11,
        'DESEMBER' => 12, 'DES' => 12, 'DEC' => 12,
    ];

    public function handle(): int
    {
        $unitArg = $this->argument('unit');
        $files   = match ($unitArg) {
            'HO-1'  => ['HO-1' => 'karyawan-ho1-full.json'],
            'HO-2'  => ['HO-2' => 'karyawan-ho2-full.json'],
            default => ['HO-1' => 'karyawan-ho1-full.json', 'HO-2' => 'karyawan-ho2-full.json'],
        };

        $project = Project::where('kode', 'ho')->first();
        if (!$project) {
            $this->error('Project HO belum ada — jalankan import:karyawan-ho dulu.');
            return 1;
        }

        $today     = now();
        $tahunAktif = (int) $today->format('Y');
        $bulanAktif = (int) $today->format('n');

        $existing = Employee::where('project_id', $project->id)->get(['id', 'nama_lengkap']);
        // unit per employee (dari employee_ho_details) — dipakai supaya "EFENDI" di HO-1 tidak
        // ketuker sama "EFENDI" di HO-2 (nama sama, orang beda, unit beda).
        $unitMap  = EmployeeHoDetail::pluck('unit', 'employee_id');
        $summary  = ['matched' => 0, 'baru' => 0, 'jabatan_diisi' => 0, 'riwayat_gaji' => 0, 'payroll_diisi' => 0];

        foreach ($files as $unit => $filename) {
            $path = database_path('data/' . $filename);
            if (!file_exists($path)) {
                $this->error("File tidak ditemukan: {$filename}");
                continue;
            }
            $data = json_decode(file_get_contents($path), true);
            if (!$data) {
                $this->error("Gagal membaca file JSON: {$filename}");
                continue;
            }

            foreach ($data['employees'] as $row) {
                $identitas = $row['identitas'] ?? [];
                $namaBaru  = trim((string) ($identitas['NAMA KARYAWAN'] ?? ''));
                if (!$namaBaru) continue;

                $employee = $this->cocokkanKaryawan($namaBaru, $existing, $unitMap, $unit);

                if (!$employee) {
                    $position = $this->resolvePosition($identitas['JABATAN'] ?? null);
                    $employee = Employee::create([
                        'nama_lengkap' => $namaBaru,
                        'position_id'  => $position?->id,
                        'project_id'   => $project->id,
                        'group'        => 'AKM',
                        'status'       => 'AKTIF',
                        'no_rekening'  => $this->bersihkanRekening($identitas['NOREK'] ?? $identitas['NO.REKENING'] ?? null),
                        'nama_bank'    => $identitas['NAMA BANK'] ?? $identitas['BANK'] ?? null,
                        'ptkp'         => $identitas['STATUS PTKP'] ?? $identitas['STATUS'] ?? null,
                    ]);
                    $existing->push($employee);
                    $summary['baru']++;
                } else {
                    $summary['matched']++;
                    $updates = [];
                    if (empty($employee->position_id) && !empty($identitas['JABATAN'])) {
                        $position = $this->resolvePosition($identitas['JABATAN']);
                        $updates['position_id'] = $position?->id;
                        $summary['jabatan_diisi']++;
                    }
                    if (empty($employee->no_rekening)) {
                        $rek = $this->bersihkanRekening($identitas['NOREK'] ?? $identitas['NO.REKENING'] ?? null);
                        if ($rek) $updates['no_rekening'] = $rek;
                    }
                    if (empty($employee->nama_bank) && !empty($identitas['NAMA BANK'] ?? $identitas['BANK'] ?? null)) {
                        $updates['nama_bank'] = $identitas['NAMA BANK'] ?? $identitas['BANK'];
                    }
                    if (empty($employee->ptkp) && !empty($identitas['STATUS PTKP'] ?? $identitas['STATUS'] ?? null)) {
                        $updates['ptkp'] = $identitas['STATUS PTKP'] ?? $identitas['STATUS'];
                    }
                    if ($updates) $employee->update($updates);
                }

                EmployeeHoDetail::firstOrCreate(
                    ['employee_id' => $employee->id],
                    ['unit' => $unit]
                );
                $unitMap[$employee->id] = $unitMap[$employee->id] ?? $unit;

                // ── Riwayat gaji mentah (arsip, tidak dipakai hitung apapun) ──
                $urutan = 0;
                foreach (($row['history_gaji'] ?? []) as $label => $nominal) {
                    if (!is_numeric($nominal)) continue;
                    [$tahun, $bulan] = $this->parseTahunBulan($label);
                    EmployeeSalaryHistory::updateOrCreate(
                        ['employee_id' => $employee->id, 'label' => mb_substr($label, 0, 150)],
                        ['nominal' => $nominal, 'tahun' => $tahun, 'bulan' => $bulan, 'urutan' => $urutan]
                    );
                    $urutan++;
                    $summary['riwayat_gaji']++;
                }

                // ── Gaji terkini -> isi Gaji Pokok resmi di Data Gaji ──
                $periode = $this->hitungGajiTerkini($row['gaji_terkini'] ?? []);
                foreach ($periode as [$tahun, $bulan, $nominal]) {
                    EmployeePayroll::updateOrCreate(
                        ['employee_id' => $employee->id, 'tahun' => $tahun, 'bulan' => $bulan],
                        ['gaji_pokok' => $nominal, 'dibuat_oleh' => 'Import HO Full']
                    );
                    $summary['payroll_diisi']++;
                }
                // Bawa nilai gaji terkini yang paling baru ke periode AKTIF (bulan berjalan) juga,
                // supaya Data Gaji bulan ini langsung terisi tanpa nunggu HRD input manual.
                if (!empty($periode)) {
                    $terakhir = end($periode);
                    [, , $nominalTerakhir] = $terakhir;
                    $sudahAdaPeriodeAktif = collect($periode)->contains(fn ($p) => $p[0] === $tahunAktif && $p[1] === $bulanAktif);
                    if (!$sudahAdaPeriodeAktif) {
                        EmployeePayroll::updateOrCreate(
                            ['employee_id' => $employee->id, 'tahun' => $tahunAktif, 'bulan' => $bulanAktif],
                            ['gaji_pokok' => $nominalTerakhir, 'dibuat_oleh' => 'Import HO Full (carry-forward)']
                        );
                        $summary['payroll_diisi']++;
                    }
                }
            }
        }

        $this->table(['Keterangan', 'Jumlah'], [
            ['Karyawan cocok (existing)', $summary['matched']],
            ['Karyawan baru dibuat', $summary['baru']],
            ['Jabatan baru diisi', $summary['jabatan_diisi']],
            ['Baris riwayat gaji diimport', $summary['riwayat_gaji']],
            ['Baris Data Gaji (Gaji Pokok) diisi/update', $summary['payroll_diisi']],
        ]);

        return 0;
    }

    // Cocokkan nama dari file baru terhadap karyawan HO yang sudah ada, berdasarkan nama
    // yang dinormalisasi (buang gelar/singkatan/tanda baca) — tidak ada field ID yang bisa
    // dipakai sebagai kunci pasti antar kedua sumber data. Kalau nama yang sama muncul di
    // KEDUA unit (mis. "Efendi" di HO-1 dan HO-2 adalah 2 orang berbeda), prioritaskan
    // kandidat yang unit-nya sama dulu — baru fallback ke kandidat unit lain/tanpa unit.
    private function cocokkanKaryawan(string $namaBaru, $existing, $unitMap, string $unitSaatIni): ?Employee
    {
        $target = $this->normalisasiNama($namaBaru);
        if (!$target) return null;

        $kandidat = [];
        foreach ($existing as $emp) {
            if ($this->normalisasiNama($emp->nama_lengkap) === $target) {
                $kandidat[] = $emp;
            }
        }
        if (empty($kandidat)) return null;
        if (count($kandidat) === 1) return $kandidat[0];

        // Ambigu (>1 kandidat) — cari yang unit-nya persis sama dengan file yang sedang diproses.
        foreach ($kandidat as $emp) {
            if (($unitMap[$emp->id] ?? null) === $unitSaatIni) {
                return $emp;
            }
        }
        // Tidak ada yang unit-nya cocok — jangan asal tebak salah satu, biar aman dibuat baru.
        return null;
    }

    private function normalisasiNama(string $nama): string
    {
        $nama = strtoupper($nama);
        // Titik/koma DIHAPUS langsung (bukan diganti spasi) supaya singkatan gelar yang nempel
        // huruf seperti "S.Kom"/"A.Md" tetap jadi 1 token ("SKOM"/"AMD"), bukan pecah 2 kata.
        $nama = str_replace(['.', ','], '', $nama);
        $nama = str_replace(['(', ')', '/'], ' ', $nama);
        // Buang gelar/singkatan akademik umum supaya "RAHMAT SJUKRI SKOM" == "RAHMAT SJUKRI".
        $gelar = [
            'H','HJ','IR','DRS','DRA','DR','ST','SE','SH','SSOS','SKOM','SAK','MSI','MM','MT','MSC','SPD',
            'AMD','SAG','AK','SIKOM','STRKOM','SIP','SFARM','SKG','SPT','SHUT','MPD','MKOM','MARS',
        ];
        $kata  = preg_split('/\s+/', trim($nama));
        $kata  = array_filter($kata, fn ($k) => $k !== '' && !in_array($k, $gelar, true));
        sort($kata); // urutan kata diabaikan (mis. "YULIADI / ALDI" vs "ALDI YULIADI")
        return implode(' ', $kata);
    }

    private function resolvePosition(?string $jabatan): ?Position
    {
        if (!$jabatan) return null;
        $existing = Position::whereRaw('LOWER(nama_jabatan) = ?', [strtolower(trim($jabatan))])->first();
        if ($existing) return $existing;
        return Position::create(['nama_jabatan' => ucwords(strtolower(trim($jabatan))), 'is_active' => true]);
    }

    private function bersihkanRekening(mixed $val): ?string
    {
        if ($val === null || $val === '') return null;
        $val = ltrim((string) $val, "'");
        $val = preg_replace('/\.0$/', '', $val);
        return $val ?: null;
    }

    // Best-effort: cari tahun (4 digit, 20xx) dan nama bulan Indonesia di dalam label kolom.
    // Balikin [null, null] kalau tidak yakin — jangan menebak tanggal yang salah.
    private function parseTahunBulan(string $label): array
    {
        $upper = strtoupper($label);
        $tahun = null;
        if (preg_match('/\b(20[12]\d)\b/', $upper, $m)) {
            $tahun = (int) $m[1];
        }
        $bulan = null;
        foreach (self::BULAN_MAP as $nama => $angka) {
            if (preg_match('/\b' . $nama . '\b/', $upper)) {
                $bulan = $angka;
                break;
            }
        }
        return [$tahun, $bulan];
    }

    // gaji_terkini: key "gaji_<bulan>_<tahun>" = nilai absolut, "kenaikan_<bulan>_<tahun>" = delta
    // yang ditambahkan ke nilai absolut terakhir yang diketahui. Balikin list [tahun, bulan, nominal]
    // terurut kronologis.
    private function hitungGajiTerkini(array $gajiTerkini): array
    {
        $entries = [];
        foreach ($gajiTerkini as $key => $value) {
            if ($value === null) continue;
            if (!preg_match('/^(gaji|kenaikan)_([a-z]+)_(\d{4})$/', $key, $m)) continue;
            $tipe  = $m[1];
            $bulan = self::BULAN_MAP[strtoupper($m[2])] ?? null;
            $tahun = (int) $m[3];
            if (!$bulan) continue;
            $entries[] = ['tipe' => $tipe, 'tahun' => $tahun, 'bulan' => $bulan, 'nilai' => (float) $value];
        }
        usort($entries, fn ($a, $b) => ($a['tahun'] * 12 + $a['bulan']) <=> ($b['tahun'] * 12 + $b['bulan']));

        $hasil = [];
        $running = null;
        foreach ($entries as $e) {
            $running = $e['tipe'] === 'kenaikan' ? ($running ?? 0) + $e['nilai'] : $e['nilai'];
            $hasil[] = [$e['tahun'], $e['bulan'], $running];
        }
        return $hasil;
    }
}
