<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Employee;
use App\Models\EmployeePayroll;

class FixPayrollNk extends Command
{
    protected $signature   = 'fix:payroll-nk';
    protected $description = 'Fix semua nilai payroll NK April 2026 sesuai Excel';

    public function handle(): int
    {
        $PROJECT_ID = 5;
        $TAHUN      = 2026;
        $BULAN      = 4;

        // Data persis dari Excel DATA GAJI
        // field: gapok, tunj_tetap, komp_kontrak, insentif, lump_sum,
        //        com_day, jml_jam_lembur, upah_lembur, h_kerja,
        //        gaji_kotor, gaji_bersih, izin, sakit, alpa, cuti
        $data = [
            'RINALDI' => [
                'gaji_pokok'=>3825000,'tunj_tetap'=>200000,'komp_kontrak'=>335417,
                'insentif'=>0,'lump'=>0,'com_day'=>0,
                'jml_jam_lembur'=>123.5,'upah_lembur'=>2873338.15,
                'h_kerja'=>25,'gaji_kotor'=>7233754.82,'gaji_bersih'=>7113004.82,
                'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>0,
            ],
            'ZULBAID' => [
                'gaji_pokok'=>3800000,'tunj_tetap'=>200000,'komp_kontrak'=>333333,
                'insentif'=>0,'lump'=>0,'com_day'=>0,
                'jml_jam_lembur'=>123.5,'upah_lembur'=>2855491.33,
                'h_kerja'=>25,'gaji_kotor'=>7188824.66,'gaji_bersih'=>7068824.66,
                'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>0,
            ],
            'ZUL HENDRI' => [
                'gaji_pokok'=>3825000,'tunj_tetap'=>200000,'komp_kontrak'=>335417,
                'insentif'=>0,'lump'=>0,'com_day'=>0,
                'jml_jam_lembur'=>81.5,'upah_lembur'=>1896170.52,
                'h_kerja'=>25,'gaji_kotor'=>6256587.19,'gaji_bersih'=>6096297.19,
                'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>0,
            ],
            'FAHRIZALDI' => [
                'gaji_pokok'=>3800000,'tunj_tetap'=>200000,'komp_kontrak'=>333333,
                'insentif'=>0,'lump'=>0,'com_day'=>0,
                'jml_jam_lembur'=>119.5,'upah_lembur'=>2763005.78,
                'h_kerja'=>24,'gaji_kotor'=>7096339.11,'gaji_bersih'=>6936799.11,
                'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>0,
            ],
            'DWI PUTRA ARDIANSYAH' => [
                'gaji_pokok'=>3800000,'tunj_tetap'=>200000,'komp_kontrak'=>333333,
                'insentif'=>0,'lump'=>0,'com_day'=>0,
                'jml_jam_lembur'=>115.5,'upah_lembur'=>2670520.23,
                'h_kerja'=>21,'gaji_kotor'=>7003853.56,'gaji_bersih'=>6883853.56,
                'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>0,
            ],
            'MUHAMMAD BASTIAN' => [
                'gaji_pokok'=>3754000,'tunj_tetap'=>200000,'komp_kontrak'=>329500,
                'insentif'=>1300000,'lump'=>200000,'com_day'=>0,
                'jml_jam_lembur'=>0,'upah_lembur'=>0,
                'h_kerja'=>24,'gaji_kotor'=>5783500,'gaji_bersih'=>5664880,
                'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>3,
            ],
            'EGA SULISTIO FEBRIANSYAH' => [
                'gaji_pokok'=>3754000,'tunj_tetap'=>200000,'komp_kontrak'=>329500,
                'insentif'=>1300000,'lump'=>150000,'com_day'=>0,
                'jml_jam_lembur'=>0,'upah_lembur'=>0,
                'h_kerja'=>25,'gaji_kotor'=>5733500,'gaji_bersih'=>5614880,
                'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>3,
            ],
            'DODI CANDRA' => [
                'gaji_pokok'=>3754000,'tunj_tetap'=>200000,'komp_kontrak'=>329500,
                'insentif'=>1300000,'lump'=>200000,'com_day'=>0,
                'jml_jam_lembur'=>0,'upah_lembur'=>0,
                'h_kerja'=>25,'gaji_kotor'=>5783500,'gaji_bersih'=>5664880,
                'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>2,
            ],
            'SANRISE PRAMANA' => [
                'gaji_pokok'=>3754000,'tunj_tetap'=>200000,'komp_kontrak'=>329500,
                'insentif'=>1300000,'lump'=>200000,'com_day'=>0,
                'jml_jam_lembur'=>0,'upah_lembur'=>0,
                'h_kerja'=>25,'gaji_kotor'=>5783500,'gaji_bersih'=>5506720,
                'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>2,
            ],
            'ILHAM DANIL' => [
                'gaji_pokok'=>3754000,'tunj_tetap'=>200000,'komp_kontrak'=>329500,
                'insentif'=>1300000,'lump'=>200000,'com_day'=>0,
                'jml_jam_lembur'=>0,'upah_lembur'=>0,
                'h_kerja'=>25,'gaji_kotor'=>5783500,'gaji_bersih'=>5664880,
                'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>0,
            ],
            'RIKI CANDIKA' => [
                'gaji_pokok'=>3754000,'tunj_tetap'=>200000,'komp_kontrak'=>329500,
                'insentif'=>1300000,'lump'=>200000,'com_day'=>0,
                'jml_jam_lembur'=>0,'upah_lembur'=>0,
                'h_kerja'=>24,'gaji_kotor'=>5783500,'gaji_bersih'=>5664880,
                'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>0,
            ],
            'FAHRI MUHAMMAD RAYHAN' => [
                'gaji_pokok'=>3754000,'tunj_tetap'=>200000,'komp_kontrak'=>329500,
                'insentif'=>628000,'lump'=>0,'com_day'=>0,
                'jml_jam_lembur'=>0,'upah_lembur'=>0,
                'h_kerja'=>25,'gaji_kotor'=>4911500,'gaji_bersih'=>4792880,
                'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>0,
            ],
            'NICO PRASETYO' => [
                'gaji_pokok'=>3800000,'tunj_tetap'=>200000,'komp_kontrak'=>333333,
                'insentif'=>0,'lump'=>0,'com_day'=>0,
                'jml_jam_lembur'=>127.5,'upah_lembur'=>2947976.88,
                'h_kerja'=>23,'gaji_kotor'=>7281310.21,'gaji_bersih'=>7123150.21,
                'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>0,
            ],
            'RIFALDI' => [
                'gaji_pokok'=>3800000,'tunj_tetap'=>200000,'komp_kontrak'=>333333,
                'insentif'=>1600000,'lump'=>400000,'com_day'=>0,
                'jml_jam_lembur'=>0,'upah_lembur'=>0,
                'h_kerja'=>25,'gaji_kotor'=>6333333.33,'gaji_bersih'=>6214713.33,
                'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>0,
            ],
            'MUHAMMAD FIRDAUS RIZAL' => [
                'gaji_pokok'=>3754000,'tunj_tetap'=>200000,'komp_kontrak'=>329500,
                'insentif'=>1550000,'lump'=>300000,'com_day'=>0,
                'jml_jam_lembur'=>0,'upah_lembur'=>0,
                'h_kerja'=>24,'gaji_kotor'=>6133500,'gaji_bersih'=>6014880,
                'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>0,
            ],
            'FAHRUROZI' => [
                'gaji_pokok'=>3754000,'tunj_tetap'=>200000,'komp_kontrak'=>329500,
                'insentif'=>1300000,'lump'=>200000,'com_day'=>0,
                'jml_jam_lembur'=>0,'upah_lembur'=>0,
                'h_kerja'=>25,'gaji_kotor'=>5783500,'gaji_bersih'=>5664880,
                'izin'=>0,'sakit'=>2,'alpa'=>0,'cuti'=>0,
            ],
            'JOHANES' => [
                'gaji_pokok'=>3754000,'tunj_tetap'=>200000,'komp_kontrak'=>329500,
                'insentif'=>1300000,'lump'=>200000,'com_day'=>0,
                'jml_jam_lembur'=>0,'upah_lembur'=>0,
                'h_kerja'=>25,'gaji_kotor'=>5783500,'gaji_bersih'=>5664880,
                'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>3,
            ],
            'ARI PRADANA' => [
                'gaji_pokok'=>3754000,'tunj_tetap'=>200000,'komp_kontrak'=>329500,
                'insentif'=>1300000,'lump'=>200000,'com_day'=>0,
                'jml_jam_lembur'=>0,'upah_lembur'=>0,
                'h_kerja'=>25,'gaji_kotor'=>5783500,'gaji_bersih'=>5664880,
                'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>0,
            ],
            'M REZA SYAH FAHLEVI' => [
                'gaji_pokok'=>3800000,'tunj_tetap'=>200000,'komp_kontrak'=>333333,
                'insentif'=>1550000,'lump'=>320000,'com_day'=>0,
                'jml_jam_lembur'=>0,'upah_lembur'=>0,
                'h_kerja'=>25,'gaji_kotor'=>6203333.33,'gaji_bersih'=>6045173.33,
                'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>0,
            ],
            'THIO BUKI' => [
                'gaji_pokok'=>3754000,'tunj_tetap'=>200000,'komp_kontrak'=>329500,
                'insentif'=>1300000,'lump'=>200000,'com_day'=>0,
                'jml_jam_lembur'=>0,'upah_lembur'=>0,
                'h_kerja'=>25,'gaji_kotor'=>5783500,'gaji_bersih'=>5664880,
                'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>0,
            ],
            'DANIL DANI' => [
                'gaji_pokok'=>3754000,'tunj_tetap'=>200000,'komp_kontrak'=>329500,
                'insentif'=>628000,'lump'=>0,'com_day'=>0,
                'jml_jam_lembur'=>0,'upah_lembur'=>0,
                'h_kerja'=>25,'gaji_kotor'=>4911500,'gaji_bersih'=>4595180,
                'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>0,
            ],
            'SONI' => [
                'gaji_pokok'=>3825000,'tunj_tetap'=>200000,'komp_kontrak'=>335417,
                'insentif'=>0,'lump'=>0,'com_day'=>0,
                'jml_jam_lembur'=>109.0,'upah_lembur'=>2535982.66,
                'h_kerja'=>23,'gaji_kotor'=>6896399.33,'gaji_bersih'=>6775649.33,
                'izin'=>0,'sakit'=>2,'alpa'=>0,'cuti'=>0,
            ],
            'WIRA DUTA INDRASTATA' => [
                'gaji_pokok'=>3754000,'tunj_tetap'=>200000,'komp_kontrak'=>329500,
                'insentif'=>1300000,'lump'=>200000,'com_day'=>0,
                'jml_jam_lembur'=>0,'upah_lembur'=>0,
                'h_kerja'=>25,'gaji_kotor'=>5783500,'gaji_bersih'=>5664880,
                'izin'=>0,'sakit'=>1,'alpa'=>0,'cuti'=>0,
            ],
            // Ali Muda Rambe: tunj_tetap = transport 200k + jabatan 450k = 650k, com_day=1050000
            'ALI MUDA RAMBE' => [
                'gaji_pokok'=>3560000,'tunj_tetap'=>650000,'komp_kontrak'=>350833,
                'insentif'=>1000000,'lump'=>0,'com_day'=>1050000,
                'jml_jam_lembur'=>0,'upah_lembur'=>0,
                'h_kerja'=>26,'gaji_kotor'=>6610833.33,'gaji_bersih'=>6610833.33,
                'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>0,
            ],
            'DIRGA MARIELDO ALRAHMAN' => [
                'gaji_pokok'=>3800000,'tunj_tetap'=>200000,'komp_kontrak'=>333333,
                'insentif'=>0,'lump'=>0,'com_day'=>0,
                'jml_jam_lembur'=>122.0,'upah_lembur'=>2820809.25,
                'h_kerja'=>24,'gaji_kotor'=>7154142.58,'gaji_bersih'=>6994142.58,
                'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>0,
            ],
            'RIZANDY MARSYA' => [
                'gaji_pokok'=>3800000,'tunj_tetap'=>200000,'komp_kontrak'=>333333,
                'insentif'=>0,'lump'=>0,'com_day'=>0,
                'jml_jam_lembur'=>99.0,'upah_lembur'=>2289017.34,
                'h_kerja'=>24,'gaji_kotor'=>6622350.67,'gaji_bersih'=>6474350.67,
                'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>0,
            ],
            'RIAN FITRA' => [
                'gaji_pokok'=>3754000,'tunj_tetap'=>200000,'komp_kontrak'=>329500,
                'insentif'=>1300000,'lump'=>200000,'com_day'=>0,
                'jml_jam_lembur'=>0,'upah_lembur'=>0,
                'h_kerja'=>1,'gaji_kotor'=>5783500,'gaji_bersih'=>5711500,
                'izin'=>0,'sakit'=>3,'alpa'=>0,'cuti'=>0,
            ],
            'SOPIAN' => [
                'gaji_pokok'=>3825000,'tunj_tetap'=>200000,'komp_kontrak'=>335417,
                'insentif'=>0,'lump'=>0,'com_day'=>0,
                'jml_jam_lembur'=>112.5,'upah_lembur'=>2617413.29,
                'h_kerja'=>0,'gaji_kotor'=>6977829.96,'gaji_bersih'=>6816829.96,
                'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>0,
            ],
            'UZAIR ATTAMIMI' => [
                'gaji_pokok'=>3754000,'tunj_tetap'=>200000,'komp_kontrak'=>329500,
                'insentif'=>1550000,'lump'=>200000,'com_day'=>0,
                'jml_jam_lembur'=>0,'upah_lembur'=>0,
                'h_kerja'=>25,'gaji_kotor'=>6033500,'gaji_bersih'=>5875340,
                'izin'=>0,'sakit'=>2,'alpa'=>0,'cuti'=>0,
            ],
            'ARIF SEDIA LAKSANA' => [
                'gaji_pokok'=>3754000,'tunj_tetap'=>200000,'komp_kontrak'=>329500,
                'insentif'=>1300000,'lump'=>200000,'com_day'=>0,
                'jml_jam_lembur'=>0,'upah_lembur'=>0,
                'h_kerja'=>0,'gaji_kotor'=>5783500,'gaji_bersih'=>5664880,
                'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>0,
            ],
            'ANGGA TINAMBUNAN' => [
                'gaji_pokok'=>3754000,'tunj_tetap'=>200000,'komp_kontrak'=>329500,
                'insentif'=>1300000,'lump'=>200000,'com_day'=>0,
                'jml_jam_lembur'=>0,'upah_lembur'=>0,
                'h_kerja'=>0,'gaji_kotor'=>5783500,'gaji_bersih'=>5664880,
                'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>0,
            ],
            'M FIRMANSYAH' => [
                'gaji_pokok'=>3754000,'tunj_tetap'=>200000,'komp_kontrak'=>329500,
                'insentif'=>1300000,'lump'=>200000,'com_day'=>0,
                'jml_jam_lembur'=>0,'upah_lembur'=>0,
                'h_kerja'=>0,'gaji_kotor'=>5783500,'gaji_bersih'=>5664880,
                'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>0,
            ],
        ];

        $updated = 0;
        $skipped = 0;
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

            $upahPenuh       = $d['gaji_pokok'] + $d['tunj_tetap'];
            $kompPwt         = round($upahPenuh / 12, 2);
            $potonganJht     = round($upahPenuh * 0.02);
            $potonganPensiun = round($upahPenuh * 0.01);
            $potonganKes     = round($upahPenuh * 0.01);

            EmployeePayroll::updateOrCreate(
                [
                    'employee_id' => $emp->id,
                    'tahun'       => $TAHUN,
                    'bulan'       => $BULAN,
                ],
                [
                    'gaji_pokok'            => $d['gaji_pokok'],
                    'tunj_tetap'            => $d['tunj_tetap'],
                    'kompensasi_pwt'        => $kompPwt,
                    'kompensasi_kontrak'    => $d['komp_kontrak'],
                    'insentif'              => $d['insentif'],
                    'com_day'               => $d['com_day'],
                    'total_lembur_flat'     => $d['lump'],
                    'tunj_makan'            => 0,
                    'tunj_produksi'         => 0,
                    'tunj_lapangan'         => 0,
                    'tunj_kehadiran'        => 0,
                    'tunj_pulsa'            => 0,
                    'jml_jam_lembur'        => $d['jml_jam_lembur'],
                    'upah_lembur'           => $d['upah_lembur'],
                    'l_sabtu'               => 0,
                    'l_libur'               => 0,
                    'lembur_biasa'          => 0,
                    'uang_hadir'            => 0,
                    'h_kerja'               => $d['h_kerja'],
                    'gaji_kotor'            => $d['gaji_kotor'],
                    'potongan_jht'          => $potonganJht,
                    'potongan_pensiun'      => $potonganPensiun,
                    'potongan_kes'          => $potonganKes,
                    'potongan_alpa'         => 0,
                    'kekurangan_bulan_lalu' => 0,
                    'gaji_bersih'           => $d['gaji_bersih'],
                    'izin'                  => $d['izin'],
                    'sakit'                 => $d['sakit'],
                    'alpa'                  => $d['alpa'],
                    'cuti'                  => $d['cuti'],
                    'dibuat_oleh'           => 'Fix Payroll NK April 2026',
                ]
            );

            $updated++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Selesai! {$updated} payroll diupdate, {$skipped} dilewati.");
        $this->newLine();
        $this->line("Catatan Ali Muda Rambe:");
        $this->line("  tunj_tetap = 650.000 (transport 200k + jabatan 450k)");
        $this->line("  com_day    = 1.050.000");
        $this->line("  insentif   = 1.000.000");
        return 0;
    }
}