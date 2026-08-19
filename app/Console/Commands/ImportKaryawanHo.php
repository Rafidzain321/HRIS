<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\EmployeeHoDetail;
use App\Models\Position;
use App\Models\Project;
use Illuminate\Console\Command;

class ImportKaryawanHo extends Command
{
    protected $signature   = 'import:karyawan-ho {unit=all : HO-1, HO-2, atau all}';
    protected $description = 'Import data identitas karyawan Head Office (HO-1/HO-2) dari file JSON';

    public function handle(): int
    {
        $unitArg = $this->argument('unit');
        $files   = match ($unitArg) {
            'HO-1'  => ['HO-1' => 'karyawan-ho1.json'],
            'HO-2'  => ['HO-2' => 'karyawan-ho2.json'],
            default => ['HO-1' => 'karyawan-ho1.json', 'HO-2' => 'karyawan-ho2.json'],
        };

        $project = Project::firstOrCreate(
            ['kode' => 'ho'],
            [
                'nama'           => 'Head Office',
                'lokasi'         => 'Pekanbaru',
                'tipe_timesheet' => '7jam',
                'tipe_gaji'      => 'ho',
                'warna'          => '#6B7280',
                'is_active'      => true,
            ]
        );
        if ($project->tipe_gaji !== 'ho') {
            $project->update(['tipe_gaji' => 'ho']);
        }

        $summary  = ['HO-1' => 0, 'HO-2' => 0, 'employee_baru' => 0, 'position_baru' => 0];

        foreach ($files as $unit => $filename) {
            $path = database_path('data/' . $filename);
            $data = json_decode(file_get_contents($path), true);

            if (!$data) {
                $this->error("Gagal membaca file JSON: {$filename}");
                continue;
            }

            foreach ($data['employees'] as $e) {
                $employee = $e['no_ktp'] ? Employee::where('no_ktp', $e['no_ktp'])->first() : null;

                if (!$employee) {
                    $position = $this->resolvePosition($e['jabatan'] ?? null);
                    if ($position && $position->wasRecentlyCreated) {
                        $summary['position_baru']++;
                    }

                    $employee = Employee::create([
                        'nama_lengkap' => $e['nama_lengkap'],
                        'no_ktp'       => $e['no_ktp'] ?? null,
                        'tempat_lahir' => $e['tempat_lahir'] ?? null,
                        'tanggal_lahir'=> $this->cleanTanggal($e['tanggal_lahir'] ?? null),
                        'alamat'       => $e['alamat'] ?? null,
                        'kota_asal'    => $e['kota_asal'] ?? null,
                        'ptkp'         => $e['ptkp'] ?? null,
                        'no_telepon'   => $e['no_telepon'] ?? null,
                        'no_rekening'  => $this->cleanRekening($e['no_rekening'] ?? null),
                        'nama_bank'    => $e['nama_bank'] ?? null,
                        'no_bpjs_tk'   => $e['no_bpjs_tk'] ?? null,
                        'no_bpjs_kes'  => $e['no_bpjs_kes'] ?? null,
                        'position_id'  => $position?->id,
                        'project_id'   => $project->id,
                        'group'        => 'AKM',
                        'status'       => 'AKTIF',
                        'tanggal_masuk'=> $this->cleanTanggal($e['tanggal_masuk'] ?? null),
                    ]);
                    $summary['employee_baru']++;
                } else {
                    // Karyawan sudah ada — jangan timpa field yang sudah terisi, cuma isi yang masih kosong.
                    $fillIfEmpty = [
                        'tempat_lahir'  => $e['tempat_lahir'] ?? null,
                        'tanggal_lahir' => $this->cleanTanggal($e['tanggal_lahir'] ?? null),
                        'alamat'        => $e['alamat'] ?? null,
                        'kota_asal'     => $e['kota_asal'] ?? null,
                        'ptkp'          => $e['ptkp'] ?? null,
                        'no_telepon'    => $e['no_telepon'] ?? null,
                        'no_rekening'   => $this->cleanRekening($e['no_rekening'] ?? null),
                        'nama_bank'     => $e['nama_bank'] ?? null,
                        'no_bpjs_tk'    => $e['no_bpjs_tk'] ?? null,
                        'no_bpjs_kes'   => $e['no_bpjs_kes'] ?? null,
                        'tanggal_masuk' => $this->cleanTanggal($e['tanggal_masuk'] ?? null),
                    ];
                    $updates = [];
                    foreach ($fillIfEmpty as $field => $value) {
                        if (empty($employee->$field) && !empty($value)) {
                            $updates[$field] = $value;
                        }
                    }
                    if (empty($employee->position_id) && !empty($e['jabatan'])) {
                        $position = $this->resolvePosition($e['jabatan']);
                        if ($position && $position->wasRecentlyCreated) {
                            $summary['position_baru']++;
                        }
                        $updates['position_id'] = $position?->id;
                    }
                    if ($updates) {
                        $employee->update($updates);
                    }
                }

                EmployeeHoDetail::updateOrCreate(
                    ['employee_id' => $employee->id],
                    [
                        'unit'            => $unit,
                        'nik_ho'          => $e['nik_ho'] ?? null,
                        'lokasi_kerja'    => $e['lokasi_kerja'] ?? null,
                        'status_karyawan' => $e['status_karyawan'] ?? null,
                        'nama_ktp'        => $e['nama_ktp'] ?? null,
                        'no_kk'           => $e['no_kk'] ?? null,
                        'rt_rw'           => $e['rt_rw'] ?? null,
                        'kelurahan'       => $e['kelurahan'] ?? null,
                        'kecamatan'       => $e['kecamatan'] ?? null,
                        'propinsi'        => $e['propinsi'] ?? null,
                        'npwp'            => $e['npwp'] ?? null,
                        'email'           => $e['email'] ?? null,
                    ]
                );

                $summary[$unit]++;
            }
        }

        $this->table(['Keterangan', 'Jumlah'], [
            ['HO-1 diproses', $summary['HO-1']],
            ['HO-2 diproses', $summary['HO-2']],
            ['Karyawan baru dibuat', $summary['employee_baru']],
            ['Posisi baru dibuat', $summary['position_baru']],
        ]);

        return 0;
    }

    private function resolvePosition(?string $jabatan): ?Position
    {
        if (!$jabatan) {
            return null;
        }

        $existing = Position::whereRaw('LOWER(nama_jabatan) = ?', [strtolower($jabatan)])->first();
        if ($existing) {
            return $existing;
        }

        return Position::create([
            'nama_jabatan' => ucwords(strtolower($jabatan)),
            'is_active'    => true,
        ]);
    }

    private function cleanRekening(mixed $val): ?string
    {
        if ($val === null || $val === '') {
            return null;
        }
        $val = ltrim((string) $val, "'");
        $val = preg_replace('/\.0$/', '', $val);
        return $val ?: null;
    }

    private function cleanTanggal(?string $val): ?string
    {
        if (!$val) {
            return null;
        }
        // Beberapa tanggal di sumber data formatnya bebas (mis. "10 DES 1992") — kalau tidak
        // cocok format Y-m-d, biarkan kosong daripada gagal/salah tanggal (HRD lengkapi manual).
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $val) ? $val : null;
    }
}
