<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Employee;
use App\Models\EmployeePayroll;

class UpdateInsentifNk extends Command
{
    protected $signature   = 'update:insentif-nk';
    protected $description = 'Update insentif dan total lembur lump sum NK April 2026';

    public function handle(): int
    {
        $PROJECT_ID = 5;
        $TAHUN      = 2026;
        $BULAN      = 4;

        // nama_lengkap_di_db => [insentif, total_lembur_flat]
        $data = [
            'MUHAMMAD BASTIAN'          => ['insentif' => 1300000, 'lump' => 200000],
            'EGA SULISTIO FEBRIANSYAH'  => ['insentif' => 1300000, 'lump' => 150000],
            'DODI CANDRA'               => ['insentif' => 1300000, 'lump' => 200000],
            'SANRISE PRAMANA'           => ['insentif' => 1300000, 'lump' => 200000],
            'ILHAM DANIL'               => ['insentif' => 1300000, 'lump' => 200000],
            'RIKI CANDIKA'              => ['insentif' => 1300000, 'lump' => 200000],
            'FAHRI MUHAMMAD RAYHAN'     => ['insentif' =>  628000, 'lump' =>      0],
            'RIFALDI'                   => ['insentif' => 1600000, 'lump' => 400000],
            'MUHAMMAD FIRDAUS RIZAL'    => ['insentif' => 1550000, 'lump' => 300000],
            'FAHRUROZI'                 => ['insentif' => 1300000, 'lump' => 200000],
            'JOHANES'                   => ['insentif' => 1300000, 'lump' => 200000],
            'ARI PRADANA'               => ['insentif' => 1300000, 'lump' => 200000],
            'M REZA SYAH FAHLEVI'       => ['insentif' => 1550000, 'lump' => 320000],
            'THIO BUKI'                 => ['insentif' => 1300000, 'lump' => 200000],
            'DANIL DANI'                => ['insentif' =>  628000, 'lump' =>      0],
            'WIRA DUTA INDRASTATA'      => ['insentif' => 1300000, 'lump' => 200000],
            'ALI MUDA RAMBE'            => ['insentif' => 1000000, 'lump' =>      0],
            'RIAN FITRA'                => ['insentif' => 1300000, 'lump' => 200000],
            'UZAIR ATTAMIMI'            => ['insentif' => 1550000, 'lump' => 200000],
            'ARIF SEDIA LAKSANA'        => ['insentif' => 1300000, 'lump' => 200000],
            'ANGGA TINAMBUNAN'          => ['insentif' => 1300000, 'lump' => 200000],
            'M FIRMANSYAH'              => ['insentif' => 1300000, 'lump' => 200000],
        ];

        $updated  = 0;
        $skipped  = 0;

        foreach ($data as $nama => $vals) {
            $emp = Employee::where('nama_lengkap', $nama)
                ->where('project_id', $PROJECT_ID)
                ->first();

            if (!$emp) {
                $this->warn("Tidak ditemukan: {$nama}");
                $skipped++;
                continue;
            }

            $payroll = EmployeePayroll::where([
                'employee_id' => $emp->id,
                'tahun'       => $TAHUN,
                'bulan'       => $BULAN,
            ])->first();

            if (!$payroll) {
                $this->warn("Payroll tidak ditemukan: {$nama}");
                $skipped++;
                continue;
            }

            // Recalculate gaji_kotor dengan insentif dan lump sum
            $gajiKotor = $payroll->gaji_kotor
                + $vals['insentif']
                + $vals['lump'];

            // Recalculate gaji_bersih
            $totalPotongan = $payroll->potongan_jht
                + $payroll->potongan_pensiun
                + $payroll->potongan_kes
                + $payroll->potongan_alpa;

            $gajiBersih = $gajiKotor - $totalPotongan + ($payroll->kekurangan_bulan_lalu ?? 0);

            $payroll->update([
                'insentif'          => $vals['insentif'],
                'total_lembur_flat' => $vals['lump'],
                'gaji_kotor'        => round($gajiKotor, 2),
                'gaji_bersih'       => round($gajiBersih, 2),
            ]);

            $this->line("  Updated: {$nama} | insentif={$vals['insentif']} | lump={$vals['lump']} | kotor=" . number_format($gajiKotor, 0, ',', '.'));
            $updated++;
        }

        $this->newLine();
        $this->info("Selesai! {$updated} diupdate, {$skipped} dilewati.");
        $this->line("Catatan: karyawan tanpa insentif/lump (Rinaldi, Zulbaid, dll) tidak perlu diupdate.");
        return 0;
    }
}