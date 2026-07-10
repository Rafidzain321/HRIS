<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Employee;
use App\Models\EmployeePayroll;
use App\Models\TimesheetMember;

class FixKelompokNk extends Command
{
    protected $signature   = 'fix:kelompok-nk';
    protected $description = 'Fix kelompok (flat/per_jam) dan total_lembur_flat NK April 2026';

    public function handle(): int
    {
        $PROJECT_ID = 5;
        $TAHUN      = 2026;
        $BULAN      = 4;

        // nama_lengkap => [kelompok, total_lembur_flat]
        // flat  = lembur dari Sheet1 (lump sum sabtu)
        // per_jam = lembur dari jam timesheet
        $data = [
            // ── FLAT (punya lump sum dari Sheet1) ────────────────
            'MUHAMMAD BASTIAN'          => ['kelompok' => 'flat', 'lump' => 200000],
            'EGA SULISTIO FEBRIANSYAH'  => ['kelompok' => 'flat', 'lump' => 150000],
            'DODI CANDRA'               => ['kelompok' => 'flat', 'lump' => 200000],
            'SANRISE PRAMANA'           => ['kelompok' => 'flat', 'lump' => 200000],
            'ILHAM DANIL'               => ['kelompok' => 'flat', 'lump' => 200000],
            'RIKI CANDIKA'              => ['kelompok' => 'flat', 'lump' => 200000],
            'RIFALDI'                   => ['kelompok' => 'flat', 'lump' => 400000],
            'MUHAMMAD FIRDAUS RIZAL'    => ['kelompok' => 'flat', 'lump' => 300000],
            'FAHRUROZI'                 => ['kelompok' => 'flat', 'lump' => 200000],
            'JOHANES'                   => ['kelompok' => 'flat', 'lump' => 200000],
            'ARI PRADANA'               => ['kelompok' => 'flat', 'lump' => 200000],
            'M REZA SYAH FAHLEVI'       => ['kelompok' => 'flat', 'lump' => 320000],
            'THIO BUKI'                 => ['kelompok' => 'flat', 'lump' => 200000],
            'WIRA DUTA INDRASTATA'      => ['kelompok' => 'flat', 'lump' => 200000],
            'RIAN FITRA'                => ['kelompok' => 'flat', 'lump' => 200000],
            'UZAIR ATTAMIMI'            => ['kelompok' => 'flat', 'lump' => 200000],
            'ARIF SEDIA LAKSANA'        => ['kelompok' => 'flat', 'lump' => 200000],
            'ANGGA TINAMBUNAN'          => ['kelompok' => 'flat', 'lump' => 200000],
            'M FIRMANSYAH'              => ['kelompok' => 'flat', 'lump' => 200000],

            // ── PER JAM (lembur dari timesheet) ──────────────────
            'RINALDI'                   => ['kelompok' => 'per_jam', 'lump' => 0],
            'ZULBAID'                   => ['kelompok' => 'per_jam', 'lump' => 0],
            'ZUL HENDRI'                => ['kelompok' => 'per_jam', 'lump' => 0],
            'FAHRIZALDI'                => ['kelompok' => 'per_jam', 'lump' => 0],
            'DWI PUTRA ARDIANSYAH'      => ['kelompok' => 'per_jam', 'lump' => 0],
            'FAHRI MUHAMMAD RAYHAN'     => ['kelompok' => 'per_jam', 'lump' => 0],
            'NICO PRASETYO'             => ['kelompok' => 'per_jam', 'lump' => 0],
            'DANIL DANI'                => ['kelompok' => 'per_jam', 'lump' => 0],
            'SONI'                      => ['kelompok' => 'per_jam', 'lump' => 0],
            'ALI MUDA RAMBE'            => ['kelompok' => 'per_jam', 'lump' => 0],
            'DIRGA MARIELDO ALRAHMAN'   => ['kelompok' => 'per_jam', 'lump' => 0],
            'RIZANDY MARSYA'            => ['kelompok' => 'per_jam', 'lump' => 0],
            'SOPIAN'                    => ['kelompok' => 'per_jam', 'lump' => 0],
        ];

        $updatedMember  = 0;
        $updatedPayroll = 0;
        $skipped        = 0;

        $bar = $this->output->createProgressBar(count($data));
        $bar->start();

        foreach ($data as $nama => $d) {
            $emp = Employee::where('nama_lengkap', $nama)
                ->where('project_id', $PROJECT_ID)
                ->first();

            if (!$emp) {
                $this->newLine();
                $this->warn("Tidak ditemukan: {$nama}");
                $skipped++;
                $bar->advance();
                continue;
            }

            // 1. Update kelompok di timesheet_members
            $updated = TimesheetMember::where('project_id', $PROJECT_ID)
                ->where('id_badge', $emp->id_badge)
                ->update(['kelompok' => $d['kelompok']]);

            if ($updated) $updatedMember++;

            // 2. Update total_lembur_flat di employee_payroll
            // Untuk flat: upah_lembur = total_lembur_flat (lump sum)
            // Untuk per_jam: upah_lembur tetap dari jam (tidak diubah)
            if ($d['kelompok'] === 'flat') {
                $payroll = EmployeePayroll::where([
                    'employee_id' => $emp->id,
                    'tahun'       => $TAHUN,
                    'bulan'       => $BULAN,
                ])->first();

                if ($payroll) {
                    // Recalculate gaji_kotor dengan lump sum
                    $gajiKotor  = $payroll->gaji_kotor
                        - ($payroll->upah_lembur ?? 0)
                        + $d['lump'];
                    $gajiBersih = $gajiKotor
                        - ($payroll->potongan_jht ?? 0)
                        - ($payroll->potongan_pensiun ?? 0)
                        - ($payroll->potongan_kes ?? 0)
                        - ($payroll->potongan_alpa ?? 0)
                        + ($payroll->kekurangan_bulan_lalu ?? 0);

                    $payroll->update([
                        'total_lembur_flat' => $d['lump'],
                        'upah_lembur'       => $d['lump'],
                        'jml_jam_lembur'    => 0,
                        'gaji_kotor'        => round($gajiKotor, 2),
                        'gaji_bersih'       => round($gajiBersih, 2),
                    ]);
                    $updatedPayroll++;
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Keterangan', 'Jumlah'],
            [
                ['TimesheetMember kelompok diupdate', $updatedMember],
                ['Payroll lump sum diupdate',         $updatedPayroll],
                ['Dilewati (tidak ditemukan)',         $skipped],
            ]
        );

        $this->newLine();
        $this->info('Kelompok flat/per_jam NK sudah difix!');
        $this->line('  Flat (19): Spotter, Helper, Survey, CMD, Swamper, dll');
        $this->line('  Per Jam (13): PmCow, Hes Man, Supervisor, dll');
        return 0;
    }
}