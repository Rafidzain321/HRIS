<?php
// File: database/seeders/PayrollJabatanConfigSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PayrollJabatanConfigSeeder extends Seeder
{
    public function run(): void
    {
        // Kelompok 1: Per Jam (HES MAN, PMCOW, INSTRUMENT, dll)
        $perJam = [
            'HES MAN', 'PMCOW', 'PMCow', 'PMCow SURVEY', 'PMCOW SURVEY',
            'INSTRUMENT', 'OPERATOR CABLE LOCETOR', 'PMCow Earth Work',
            'HES Man', 'PMCOW Earth Work',
        ];

        // Kelompok 2: Flat (SPOTTER, HELPER, FLAGMAN, SWAMPER, dll)
        $flat = [
            'SPOTTER', 'HELPER', 'FLAGMAN', 'Helper',
            'HELPER SURVEY', 'Helper Survey',
            'SWAMPER', 'SWAMPER FUEL TANK', 'SWAMPER LOW BOY',
            'SWAMPER WATER TRUCK', 'SWAMPER FT',
        ];

        foreach ($perJam as $jabatan) {
            DB::table('payroll_jabatan_config')->updateOrInsert(
                ['nama_jabatan' => $jabatan],
                ['kelompok' => 'per_jam', 'tarif_sabtu' => 0, 'tarif_libur' => 0, 'tarif_biasa' => 0]
            );
        }

        foreach ($flat as $jabatan) {
            DB::table('payroll_jabatan_config')->updateOrInsert(
                ['nama_jabatan' => $jabatan],
                ['kelompok' => 'flat', 'tarif_sabtu' => 75000, 'tarif_libur' => 200000, 'tarif_biasa' => 20000]
            );
        }
    }
}