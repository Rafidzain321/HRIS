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

class ImportGiamStaffMechanicalMaret2026 extends Command
{
    protected $signature   = 'import:giam-staff-mechanical-maret2026';
    protected $description = 'Import sub-group staff & mechanical GIAM beserta timesheet dan data gaji Maret 2026';

    // Jabatan sumber yang penulisannya beda dengan Posisi yang sudah ada di sistem —
    // supaya tidak membuat posisi duplikat (mis. "MATRIALMAN" vs "Material Man").
    private const POSITION_MAP = [
        'MATRIALMAN'    => 'Material Man',
        'OPERATOR IJMS' => 'IJMS Operator',
        'OPR FOCO'      => 'Foco Operator',
    ];

    private int $nextBadgeNumber = 0;

    public function handle(): int
    {
        $tahun      = 2026;
        $bulan      = 3;
        $dibuatOleh = 'Import GIAM Staff/Mechanical Maret 2026';

        $project = Project::where('kode', 'giam')->first();
        if (!$project) {
            $this->error('Project GIAM tidak ditemukan.');
            return 1;
        }

        $staffPath = database_path('data/giam-staff-maret2026.json');
        $mechPath  = database_path('data/giam-mechanical-maret2026.json');
        $staff     = json_decode(file_get_contents($staffPath), true);
        $mech      = json_decode(file_get_contents($mechPath), true);

        if (!$staff || !$mech) {
            $this->error('Gagal membaca file JSON sumber data.');
            return 1;
        }

        // Pastikan item TTT custom "Tunj Transport" terdaftar untuk GIAM,
        // supaya nilainya ikut terhitung ke gaji kotor (lihat hitungGajiKotorBersih()).
        ProjectTttItem::firstOrCreate(
            ['project_id' => $project->id, 'key' => 'tunj_transport'],
            ['label' => 'Tunj Transport', 'is_default' => false, 'aktif' => true, 'urutan' => 90]
        );

        $this->nextBadgeNumber = Employee::where('id_badge', 'like', 'AKM-EW-%')
            ->get(['id_badge'])
            ->map(fn ($e) => (int) preg_replace('/\D/', '', substr($e->id_badge, -4)))
            ->max() + 1;

        $urutan = (int) (TimesheetMember::where('project_id', $project->id)->max('urutan') ?? 0) + 1;

        $summary  = ['staff' => 0, 'mechanical' => 0, 'employee_baru' => 0, 'position_baru' => 0];
        $warnings = [];

        foreach ([['staff', $staff], ['mechanical', $mech]] as [$subGroup, $dataset]) {
            foreach ($dataset['employees'] as $e) {
                $employee = Employee::where('no_ktp', $e['nik'])->first();

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
                } else {
                    // Karyawan sudah ada — jangan timpa field yang sudah terisi, cuma isi yang masih kosong.
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

                $kelompok = $e['kelompok'] ?? 'per_jam';

                TimesheetMember::updateOrCreate(
                    ['id_badge' => $employee->id_badge, 'project_id' => $project->id],
                    [
                        'kelompok'  => $kelompok,
                        'sub_group' => $subGroup,
                        'aktif'     => true,
                        'urutan'    => $urutan++,
                    ]
                );

                foreach ($e['timesheet'] as $hari => $nilai) {
                    // "0" berarti tidak ada catatan (libur/tidak masuk) — jangan disimpan sebagai
                    // baris timesheet, karena kalau disimpan akan ikut terhitung sebagai hari hadir.
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
                        'upah_lembur'           => $e['upah_lembur'] ?? 0,
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

                $summary[$subGroup]++;
            }
        }

        $this->table(['Keterangan', 'Jumlah'], [
            ['Staff diproses', $summary['staff']],
            ['Mechanical diproses', $summary['mechanical']],
            ['Karyawan baru dibuat', $summary['employee_baru']],
            ['Posisi baru dibuat', $summary['position_baru']],
        ]);

        if ($warnings) {
            $this->newLine();
            $this->warn('PERHATIAN:');
            foreach ($warnings as $w) {
                $this->warn("  !  {$w}");
            }
        }

        return 0;
    }

    private function resolvePosition(string $jabatan): Position
    {
        $target   = self::POSITION_MAP[$jabatan] ?? $jabatan;
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
