<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\EmployeePayroll;
use App\Models\Position;
use App\Models\Project;
use App\Models\ProjectTttItem;
use App\Models\Timesheet;
use App\Models\TimesheetMember;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportGiamApril2026 extends Command
{
    protected $signature   = 'import:giam-april2026';
    protected $description = 'Import Giam staff/mechanical/default beserta timesheet dan data gaji April 2026';

    // Jabatan yang secara data memakai skema lembur Flat (harian), bukan per jam —
    // dikonfirmasi dari pola jml_jam_lembur=0 + sheet "Overtime" terpisah di Excel sumber.
    private const FLAT_JABATAN = [
        'SPOTTER', 'HELPER', 'HELPER SURVEY', 'HELPER PIPING', 'FLAGMAN',
        'SWAMPER', 'SWAMPER FUEL TANK', 'SWAMPER LOW BOY', 'SWAMPER WATER TRUCK', 'SWAMPER FT',
    ];

    private const POSITION_MAP = [
        'MATRIALMAN'    => 'Material Man',
        'OPERATOR IJMS' => 'IJMS Operator',
        'OPR FOCO'      => 'Foco Operator',
        'OPR CRANE'     => 'Operator Crane',
        'ACCES CONTROL' => 'Access Control',
        'DOKUMENT CONTROL' => 'Document Control',
        'ADMIN CONTRUCTION' => 'Admin Construction',
    ];

    private int $nextBadgeNumber = 0;

    public function handle(): int
    {
        $tahun      = 2026;
        $bulan      = 4;
        $dibuatOleh = 'Import GIAM Staff/Mechanical/Default April 2026';

        $project = Project::where('kode', 'giam')->first();
        if (!$project) {
            $this->error('Project GIAM tidak ditemukan.');
            return 1;
        }

        $sources = [
            'staff'      => storage_path('app/data-entry/giam-staff-april2026.json'),
            'mechanical' => storage_path('app/data-entry/giam-mechanical-april2026.json'),
            'default'    => storage_path('app/data-entry/giam-default-april2026.json'),
        ];

        $datasets = [];
        foreach ($sources as $key => $path) {
            $data = json_decode(file_get_contents($path), true);
            if (!$data) {
                $this->error("Gagal membaca file JSON: {$path}");
                return 1;
            }
            $datasets[$key] = $data;
        }

        $summary = [
            'staff' => 0, 'mechanical' => 0, 'default' => 0,
            'employee_baru' => 0, 'position_baru' => 0,
            'timesheet_rows' => 0, 'payroll_rows' => 0,
            'flat_count' => 0, 'per_jam_count' => 0,
        ];
        $newEmployees = [];

        DB::transaction(function () use ($project, $datasets, $tahun, $bulan, $dibuatOleh, &$summary, &$newEmployees) {
            ProjectTttItem::firstOrCreate(
                ['project_id' => $project->id, 'key' => 'tunj_transport'],
                ['label' => 'Tunj Transport', 'is_default' => false, 'aktif' => true, 'urutan' => 90]
            );

            $this->nextBadgeNumber = Employee::where('id_badge', 'like', 'AKM-EW-%')
                ->get(['id_badge'])
                ->map(fn ($e) => (int) preg_replace('/\D/', '', substr($e->id_badge, -4)))
                ->max() + 1;

            $urutan = (int) (TimesheetMember::where('project_id', $project->id)->max('urutan') ?? 0) + 1;

            foreach ($datasets as $subGroupKey => $dataset) {
                // File "default" tidak punya sub_group (null) — sesuai instruksi.
                $subGroup = $subGroupKey === 'default' ? null : $subGroupKey;

                foreach ($dataset['employees'] as $e) {
                    // Kalau NIK kosong di sumber, jangan cocokkan via no_ktp NULL — bisa nyasar ke
                    // employee lain yang juga belum punya NIK. Cocokkan via nama+project sebagai gantinya.
                    $employee = !empty($e['nik'])
                        ? Employee::where('no_ktp', $e['nik'])->first()
                        : Employee::where('project_id', $project->id)
                            ->whereRaw('UPPER(nama_lengkap) = ?', [strtoupper(trim($e['nama']))])
                            ->first();

                    if (!$employee) {
                        $position = $this->resolvePosition($e['jabatan']);
                        if ($position->wasRecentlyCreated) {
                            $summary['position_baru']++;
                        }

                        $badge = 'AKM-EW-' . str_pad((string) $this->nextBadgeNumber, 4, '0', STR_PAD_LEFT);
                        $this->nextBadgeNumber++;

                        $employee = Employee::create([
                            'id_badge'     => $badge,
                            'nama_lengkap' => $e['nama'],
                            'no_ktp'       => $e['nik'],
                            'position_id'  => $position->id,
                            'project_id'   => $project->id,
                            'group'        => 'AKM',
                            'status'       => 'AKTIF',
                            'ptkp'         => $e['ptkp'] ?? null,
                            'nama_bank'    => $e['nama_bank'] ?? null,
                            'no_rekening'  => $this->cleanRekening($e['no_rekening'] ?? null),
                        ]);
                        $summary['employee_baru']++;
                        $newEmployees[] = "{$e['nama']} ({$e['nik']}) — badge {$badge}";
                    } else {
                        $fillIfEmpty = [
                            'ptkp'        => $e['ptkp'] ?? null,
                            'nama_bank'   => $e['nama_bank'] ?? null,
                            'no_rekening' => $this->cleanRekening($e['no_rekening'] ?? null),
                        ];
                        $updates = [];
                        foreach ($fillIfEmpty as $field => $value) {
                            if (empty($employee->$field) && !empty($value)) {
                                $updates[$field] = $value;
                            }
                        }
                        if ($updates) {
                            $employee->update($updates);
                        }
                    }

                    // Kelompok: staff selalu flat; sisanya ditentukan dari jabatan
                    // (jabatan yang secara nyata pakai skema lembur harian/flat).
                    $kelompok = $subGroupKey === 'staff'
                        ? 'flat'
                        : (in_array(strtoupper(trim($e['jabatan'])), self::FLAT_JABATAN, true) ? 'flat' : 'per_jam');
                    $summary[$kelompok === 'flat' ? 'flat_count' : 'per_jam_count']++;

                    TimesheetMember::updateOrCreate(
                        ['id_badge' => $employee->id_badge, 'project_id' => $project->id],
                        [
                            'kelompok'  => $kelompok,
                            'sub_group' => $subGroup,
                            'aktif'     => true,
                            'urutan'    => $urutan++,
                        ]
                    );

                    foreach (($e['timesheet'] ?? []) as $hari => $nilai) {
                        if ($nilai === 0 || $nilai === 0.0) {
                            Timesheet::where([
                                'employee_id' => $employee->id, 'tahun' => $tahun, 'bulan' => $bulan, 'hari' => (int) $hari,
                            ])->delete();
                            continue;
                        }
                        Timesheet::updateOrCreate(
                            ['employee_id' => $employee->id, 'tahun' => $tahun, 'bulan' => $bulan, 'hari' => (int) $hari],
                            ['nilai' => $nilai]
                        );
                        $summary['timesheet_rows']++;
                    }

                    $tttCustom = [];
                    if (($e['tunj_transport'] ?? 0) > 0) {
                        $tttCustom['tunj_transport'] = $e['tunj_transport'];
                    }

                    EmployeePayroll::updateOrCreate(
                        ['employee_id' => $employee->id, 'tahun' => $tahun, 'bulan' => $bulan],
                        [
                            'gaji_pokok'            => $e['gaji_pokok'],
                            'tunj_tetap'            => $e['tunj_tetap'],
                            'kompensasi_pwt'        => $e['kompensasi_pwt'] ?? round(($e['gaji_pokok'] + $e['tunj_tetap']) / 12, 2),
                            'insentif'              => $e['insentif'] ?? 0,
                            'com_day'               => $e['com_day'] ?? 0,
                            'tunj_makan'            => $e['tunj_makan'] ?? 0,
                            'tunj_produksi'         => $e['tunj_produksi'] ?? 0,
                            'tunj_lapangan'         => $e['tunj_lapangan'] ?? 0,
                            'jml_jam_lembur'        => $kelompok === 'flat' ? 0 : ($e['jml_jam_lembur'] ?? 0),
                            'upah_lembur'           => $kelompok === 'flat' ? 0 : ($e['upah_lembur'] ?? 0),
                            'l_sabtu'               => $e['l_sabtu'] ?? 0,
                            'l_libur'               => $e['l_libur'] ?? 0,
                            'lembur_biasa'          => $e['lembur_biasa'] ?? 0,
                            'total_lembur_flat'     => $kelompok === 'flat' ? ($e['upah_lembur'] ?? 0) : 0,
                            'uang_hadir'            => $e['uang_hadir'] ?? 0,
                            'h_kerja'               => $e['h_kerja'] ?? 0,
                            'gaji_kotor'            => $e['gaji_kotor'],
                            'potongan_jht'          => $e['potongan_jht'],
                            'potongan_pensiun'      => $e['potongan_pensiun'],
                            'potongan_kes'          => $e['potongan_kes'],
                            'potongan_alpa'         => $e['potongan_alpa'] ?? 0,
                            'kekurangan_bulan_lalu' => $e['kekurangan_bulan_lalu'] ?? 0,
                            'gaji_bersih'           => $e['gaji_bersih'],
                            'izin'                  => $e['izin'] ?? 0,
                            'sakit'                 => $e['sakit'] ?? 0,
                            'alpa'                  => $e['alpa'] ?? 0,
                            'cuti'                  => $e['cuti'] ?? 0,
                            'ttt_custom'            => $tttCustom ?: null,
                            'dibuat_oleh'           => $dibuatOleh,
                        ]
                    );
                    $summary['payroll_rows']++;

                    $summary[$subGroupKey]++;
                }
            }
        });

        $this->table(['Keterangan', 'Jumlah'], [
            ['Staff diproses', $summary['staff']],
            ['Mechanical diproses', $summary['mechanical']],
            ['Default diproses', $summary['default']],
            ['— Total kelompok Flat', $summary['flat_count']],
            ['— Total kelompok Per Jam', $summary['per_jam_count']],
            ['Karyawan baru dibuat', $summary['employee_baru']],
            ['Posisi baru dibuat', $summary['position_baru']],
            ['Baris timesheet tersimpan', $summary['timesheet_rows']],
            ['Baris employee_payroll tersimpan', $summary['payroll_rows']],
        ]);

        if ($newEmployees) {
            $this->newLine();
            $this->info('Karyawan baru yang dibuat:');
            foreach ($newEmployees as $n) {
                $this->line("  - {$n}");
            }
        }

        return 0;
    }

    private function resolvePosition(string $jabatan): Position
    {
        $target   = self::POSITION_MAP[strtoupper(trim($jabatan))] ?? self::POSITION_MAP[$jabatan] ?? $jabatan;
        $existing = Position::whereRaw('LOWER(nama_jabatan) = ?', [strtolower($target)])->first();
        if ($existing) {
            return $existing;
        }

        return Position::create([
            'nama_jabatan' => ucwords(strtolower($target)),
            'is_active'    => true,
        ]);
    }

    private function cleanRekening(?string $val): ?string
    {
        if (!$val) {
            return null;
        }
        $val = ltrim($val, "'");
        $val = preg_replace('/\.0$/', '', $val);
        return $val ?: null;
    }
}
