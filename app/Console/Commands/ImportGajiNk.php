<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Employee;
use App\Models\EmployeePayroll;
use App\Models\Position;
use App\Models\Project;

class ImportGajiNk extends Command
{
    protected $signature   = 'import:gaji-nk';
    protected $description = 'Import data gaji April 2026 Project NK (Nindya Karya)';

    public function handle(): int
    {
        $PROJECT_KODE = 'NK';
        $TAHUN        = 2026;
        $BULAN        = 4;
        $DIBUAT_OLEH  = 'Import NK April 2026';

        $project = Project::where('kode', $PROJECT_KODE)->first();
        if (!$project) {
            $this->error('Project NK tidak ditemukan. Buat dulu via tinker:');
            $this->line("  \\App\\Models\\Project::create(['nama'=>'Nindya Karya (NK)','kode'=>'NK','warna'=>'#3A8FE0','tipe_gaji'=>'giam','tipe_timesheet'=>'7jam','aktif'=>true]);");
            return 1;
        }

        $projectId = $project->id;
        $this->info("Project NK ditemukan - ID: {$projectId}");
        $this->newLine();

        $data = [
            ['nama'=>'RINALDI','no_ktp'=>'1403093012790002','no_rekening'=>'1080025344327','jabatan'=>'PMCOW','gaji_pokok'=>3825000,'tunj_tetap'=>200000,'kompensasi_kontrak'=>335417,'jml_jam_lembur'=>123.5,'upah_lembur'=>2873338.15,'h_kerja'=>25,'gaji_kotor'=>7233754.82,'gaji_bersih'=>7113004.82,'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>0],
            ['nama'=>'ZULBAID','no_ktp'=>'1308042005840001','no_rekening'=>'1720006339727','jabatan'=>'HES MAN','gaji_pokok'=>3800000,'tunj_tetap'=>200000,'kompensasi_kontrak'=>333333,'jml_jam_lembur'=>123.5,'upah_lembur'=>2855491.33,'h_kerja'=>25,'gaji_kotor'=>7188824.66,'gaji_bersih'=>7068824.66,'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>0],
            ['nama'=>'ZUL HENDRI','no_ktp'=>'1403092508750009','no_rekening'=>'','jabatan'=>'PMCOW','gaji_pokok'=>3825000,'tunj_tetap'=>200000,'kompensasi_kontrak'=>335417,'jml_jam_lembur'=>81.5,'upah_lembur'=>1896170.52,'h_kerja'=>25,'gaji_kotor'=>6256587.19,'gaji_bersih'=>6096297.19,'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>0],
            ['nama'=>'FAHRIZALDI','no_ktp'=>'1407022403940003','no_rekening'=>'1720004574713','jabatan'=>'HES MAN','gaji_pokok'=>3800000,'tunj_tetap'=>200000,'kompensasi_kontrak'=>333333,'jml_jam_lembur'=>119.5,'upah_lembur'=>2763005.78,'h_kerja'=>24,'gaji_kotor'=>7096339.11,'gaji_bersih'=>6936799.11,'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>0],
            ['nama'=>'DWI PUTRA ARDIANSYAH','no_ktp'=>'1403092404740007','no_rekening'=>'1720006375523','jabatan'=>'HES MAN','gaji_pokok'=>3800000,'tunj_tetap'=>200000,'kompensasi_kontrak'=>333333,'jml_jam_lembur'=>115.5,'upah_lembur'=>2670520.23,'h_kerja'=>21,'gaji_kotor'=>7003853.56,'gaji_bersih'=>6883853.56,'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>0],
            ['nama'=>'MUHAMMAD BASTIAN','no_ktp'=>'1407090305990001','no_rekening'=>'1720006340022','jabatan'=>'SPOTTER','gaji_pokok'=>3754000,'tunj_tetap'=>200000,'kompensasi_kontrak'=>329500,'jml_jam_lembur'=>0,'upah_lembur'=>0,'h_kerja'=>24,'gaji_kotor'=>5783500,'gaji_bersih'=>5664880,'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>3],
            ['nama'=>'EGA SULISTIO FEBRIANSYAH','no_ktp'=>'1403092402030015','no_rekening'=>'1720006329546','jabatan'=>'HELPER SURVEY','gaji_pokok'=>3754000,'tunj_tetap'=>200000,'kompensasi_kontrak'=>329500,'jml_jam_lembur'=>0,'upah_lembur'=>0,'h_kerja'=>25,'gaji_kotor'=>5733500,'gaji_bersih'=>5614880,'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>3],
            ['nama'=>'DODI CANDRA','no_ktp'=>'1403090803830007','no_rekening'=>'1720006351359','jabatan'=>'HELPER SURVEY','gaji_pokok'=>3754000,'tunj_tetap'=>200000,'kompensasi_kontrak'=>329500,'jml_jam_lembur'=>0,'upah_lembur'=>0,'h_kerja'=>25,'gaji_kotor'=>5783500,'gaji_bersih'=>5664880,'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>2],
            ['nama'=>'SANRISE PRAMANA','no_ktp'=>'1407100502890001','no_rekening'=>'1720006359287','jabatan'=>'SPOTTER','gaji_pokok'=>3754000,'tunj_tetap'=>200000,'kompensasi_kontrak'=>329500,'jml_jam_lembur'=>0,'upah_lembur'=>0,'h_kerja'=>25,'gaji_kotor'=>5783500,'gaji_bersih'=>5506720,'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>2],
            ['nama'=>'ILHAM DANIL','no_ktp'=>'1306141205000003','no_rekening'=>'1110025104148','jabatan'=>'SPOTTER','gaji_pokok'=>3754000,'tunj_tetap'=>200000,'kompensasi_kontrak'=>329500,'jml_jam_lembur'=>0,'upah_lembur'=>0,'h_kerja'=>25,'gaji_kotor'=>5783500,'gaji_bersih'=>5664880,'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>0],
            ['nama'=>'RIKI CANDIKA','no_ktp'=>'1407010711040002','no_rekening'=>'1720006347332','jabatan'=>'SPOTTER','gaji_pokok'=>3754000,'tunj_tetap'=>200000,'kompensasi_kontrak'=>329500,'jml_jam_lembur'=>0,'upah_lembur'=>0,'h_kerja'=>24,'gaji_kotor'=>5783500,'gaji_bersih'=>5664880,'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>0],
            ['nama'=>'FAHRI MUHAMMAD RAYHAN','no_ktp'=>'1403090704060007','no_rekening'=>'1720005571346','jabatan'=>'SWAMPER','gaji_pokok'=>3754000,'tunj_tetap'=>200000,'kompensasi_kontrak'=>329500,'jml_jam_lembur'=>0,'upah_lembur'=>0,'h_kerja'=>25,'gaji_kotor'=>4911500,'gaji_bersih'=>4792880,'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>0],
            ['nama'=>'NICO PRASETYO','no_ktp'=>'3315170504000008','no_rekening'=>'1720003990662','jabatan'=>'PMCOW','gaji_pokok'=>3800000,'tunj_tetap'=>200000,'kompensasi_kontrak'=>333333,'jml_jam_lembur'=>127.5,'upah_lembur'=>2947976.88,'h_kerja'=>23,'gaji_kotor'=>7281310.21,'gaji_bersih'=>7123150.21,'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>0],
            ['nama'=>'RIFALDI','no_ktp'=>'1407131501980001','no_rekening'=>'1720003069798','jabatan'=>'PMCOW','gaji_pokok'=>3800000,'tunj_tetap'=>200000,'kompensasi_kontrak'=>333333,'jml_jam_lembur'=>0,'upah_lembur'=>0,'h_kerja'=>25,'gaji_kotor'=>6333333.33,'gaji_bersih'=>6214713.33,'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>0],
            ['nama'=>'MUHAMMAD FIRDAUS RIZAL','no_ktp'=>'1403091907920006','no_rekening'=>'1720004230936','jabatan'=>'INSTRUMENT SURVEY','gaji_pokok'=>3754000,'tunj_tetap'=>200000,'kompensasi_kontrak'=>329500,'jml_jam_lembur'=>0,'upah_lembur'=>0,'h_kerja'=>24,'gaji_kotor'=>6133500,'gaji_bersih'=>6014880,'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>0],
            ['nama'=>'FAHRUROZI','no_ktp'=>'1407100904980002','no_rekening'=>'1720006384756','jabatan'=>'SPOTTER','gaji_pokok'=>3754000,'tunj_tetap'=>200000,'kompensasi_kontrak'=>329500,'jml_jam_lembur'=>0,'upah_lembur'=>0,'h_kerja'=>25,'gaji_kotor'=>5783500,'gaji_bersih'=>5664880,'izin'=>0,'sakit'=>2,'alpa'=>0,'cuti'=>0],
            ['nama'=>'JOHANES','no_ktp'=>'1403090701790001','no_rekening'=>'1720006409769','jabatan'=>'TEKNISI CABLE LOCATOR','gaji_pokok'=>3754000,'tunj_tetap'=>200000,'kompensasi_kontrak'=>329500,'jml_jam_lembur'=>0,'upah_lembur'=>0,'h_kerja'=>25,'gaji_kotor'=>5783500,'gaji_bersih'=>5664880,'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>3],
            ['nama'=>'ARI PRADANA','no_ktp'=>'1218142208000001','no_rekening'=>'1720006409728','jabatan'=>'TEKNISI CABLE LOCATOR','gaji_pokok'=>3754000,'tunj_tetap'=>200000,'kompensasi_kontrak'=>329500,'jml_jam_lembur'=>0,'upah_lembur'=>0,'h_kerja'=>25,'gaji_kotor'=>5783500,'gaji_bersih'=>5664880,'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>0],
            ['nama'=>'M REZA SYAH FAHLEVI','no_ktp'=>'1403090906020862','no_rekening'=>'1720006164448','jabatan'=>'QA/QC INSPECTOR','gaji_pokok'=>3800000,'tunj_tetap'=>200000,'kompensasi_kontrak'=>333333,'jml_jam_lembur'=>0,'upah_lembur'=>0,'h_kerja'=>25,'gaji_kotor'=>6203333.33,'gaji_bersih'=>6045173.33,'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>0],
            ['nama'=>'THIO BUKI','no_ktp'=>'1306141105000005','no_rekening'=>'1110024946770','jabatan'=>'HELPER','gaji_pokok'=>3754000,'tunj_tetap'=>200000,'kompensasi_kontrak'=>329500,'jml_jam_lembur'=>0,'upah_lembur'=>0,'h_kerja'=>25,'gaji_kotor'=>5783500,'gaji_bersih'=>5664880,'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>0],
            ['nama'=>'DANIL DANI','no_ktp'=>'1403092607890001','no_rekening'=>'1720006446100','jabatan'=>'SWAMPER','gaji_pokok'=>3754000,'tunj_tetap'=>200000,'kompensasi_kontrak'=>329500,'jml_jam_lembur'=>0,'upah_lembur'=>0,'h_kerja'=>25,'gaji_kotor'=>4911500,'gaji_bersih'=>4595180,'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>0],
            ['nama'=>'SONI','no_ktp'=>'1308041608770002','no_rekening'=>'1720005701430','jabatan'=>'PMCOW','gaji_pokok'=>3825000,'tunj_tetap'=>200000,'kompensasi_kontrak'=>335417,'jml_jam_lembur'=>109.0,'upah_lembur'=>2535982.66,'h_kerja'=>23,'gaji_kotor'=>6896399.33,'gaji_bersih'=>6775649.33,'izin'=>0,'sakit'=>2,'alpa'=>0,'cuti'=>0],
            ['nama'=>'WIRA DUTA INDRASTATA','no_ktp'=>'1403091012040006','no_rekening'=>'1720006424974','jabatan'=>'SPOTTER','gaji_pokok'=>3754000,'tunj_tetap'=>200000,'kompensasi_kontrak'=>329500,'jml_jam_lembur'=>0,'upah_lembur'=>0,'h_kerja'=>25,'gaji_kotor'=>5783500,'gaji_bersih'=>5664880,'izin'=>0,'sakit'=>1,'alpa'=>0,'cuti'=>0],
            ['nama'=>'ALI MUDA RAMBE','no_ktp'=>'1403091901891208','no_rekening'=>'1080011311645','jabatan'=>'SUPERVISOR PROJECT','gaji_pokok'=>3560000,'tunj_tetap'=>200000,'kompensasi_kontrak'=>350833,'jml_jam_lembur'=>0,'upah_lembur'=>0,'h_kerja'=>26,'gaji_kotor'=>6610833.33,'gaji_bersih'=>6610833.33,'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>0],
            ['nama'=>'DIRGA MARIELDO ALRAHMAN','no_ktp'=>'1407033103920002','no_rekening'=>'1720004153484','jabatan'=>'HES MAN','gaji_pokok'=>3800000,'tunj_tetap'=>200000,'kompensasi_kontrak'=>333333,'jml_jam_lembur'=>122.0,'upah_lembur'=>2820809.25,'h_kerja'=>24,'gaji_kotor'=>7154142.58,'gaji_bersih'=>6994142.58,'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>0],
            ['nama'=>'RIZANDY MARSYA','no_ktp'=>'1403090901010008','no_rekening'=>'','jabatan'=>'HES MAN','gaji_pokok'=>3800000,'tunj_tetap'=>200000,'kompensasi_kontrak'=>333333,'jml_jam_lembur'=>99.0,'upah_lembur'=>2289017.34,'h_kerja'=>24,'gaji_kotor'=>6622350.67,'gaji_bersih'=>6474350.67,'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>0],
            ['nama'=>'RIAN FITRA','no_ktp'=>'1408021505920001','no_rekening'=>'1720006831491','jabatan'=>'HELPER','gaji_pokok'=>3754000,'tunj_tetap'=>200000,'kompensasi_kontrak'=>329500,'jml_jam_lembur'=>0,'upah_lembur'=>0,'h_kerja'=>1,'gaji_kotor'=>5783500,'gaji_bersih'=>5711500,'izin'=>0,'sakit'=>3,'alpa'=>0,'cuti'=>0],
            ['nama'=>'SOPIAN','no_ktp'=>'1403131010770012','no_rekening'=>'1720001202441','jabatan'=>'PMCOW','gaji_pokok'=>3825000,'tunj_tetap'=>200000,'kompensasi_kontrak'=>335417,'jml_jam_lembur'=>112.5,'upah_lembur'=>2617413.29,'h_kerja'=>0,'gaji_kotor'=>6977829.96,'gaji_bersih'=>6816829.96,'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>0],
            ['nama'=>'UZAIR ATTAMIMI','no_ktp'=>'1407100702010003','no_rekening'=>'1720005018561','jabatan'=>'SPOTTER','gaji_pokok'=>3754000,'tunj_tetap'=>200000,'kompensasi_kontrak'=>329500,'jml_jam_lembur'=>0,'upah_lembur'=>0,'h_kerja'=>25,'gaji_kotor'=>6033500,'gaji_bersih'=>5875340,'izin'=>0,'sakit'=>2,'alpa'=>0,'cuti'=>0],
            ['nama'=>'ARIF SEDIA LAKSANA','no_ktp'=>'1407031304000005','no_rekening'=>'1720006511762','jabatan'=>'SPOTTER','gaji_pokok'=>3754000,'tunj_tetap'=>200000,'kompensasi_kontrak'=>329500,'jml_jam_lembur'=>0,'upah_lembur'=>0,'h_kerja'=>0,'gaji_kotor'=>5783500,'gaji_bersih'=>5664880,'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>0],
            ['nama'=>'ANGGA TINAMBUNAN','no_ktp'=>'1403130210060005','no_rekening'=>'1720006492690','jabatan'=>'SPOTTER','gaji_pokok'=>3754000,'tunj_tetap'=>200000,'kompensasi_kontrak'=>329500,'jml_jam_lembur'=>0,'upah_lembur'=>0,'h_kerja'=>0,'gaji_kotor'=>5783500,'gaji_bersih'=>5664880,'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>0],
            ['nama'=>'M FIRMANSYAH','no_ktp'=>'1403090810000012','no_rekening'=>'1720006510467','jabatan'=>'SPOTTER','gaji_pokok'=>3754000,'tunj_tetap'=>200000,'kompensasi_kontrak'=>329500,'jml_jam_lembur'=>0,'upah_lembur'=>0,'h_kerja'=>0,'gaji_kotor'=>5783500,'gaji_bersih'=>5664880,'izin'=>0,'sakit'=>0,'alpa'=>0,'cuti'=>0],
        ];

        $bar      = $this->output->createProgressBar(count($data));
        $inserted = 0;
        $updated  = 0;
        $warnings = [];

        $bar->start();

        foreach ($data as $d) {
            // 1. Cari/buat jabatan
            $position = Position::firstOrCreate(
                ['nama_jabatan' => strtoupper($d['jabatan'])]
            );

            // 2. Cari employee by KTP
            $employee = Employee::where('no_ktp', $d['no_ktp'])->first();

            if (!$employee) {
                // Generate badge NK-XXXX
                $lastBadge = Employee::where('id_badge', 'like', 'NK-%')
                    ->orderByRaw("CAST(SUBSTRING(id_badge, 4) AS UNSIGNED) DESC")
                    ->value('id_badge');
                $nextNum  = $lastBadge ? ((int) substr($lastBadge, 3)) + 1 : 1;
                $badge    = 'NK-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);

                $employee = Employee::create([
                    'id_badge'    => $badge,
                    'nama_lengkap'=> $d['nama'],
                    'no_ktp'      => $d['no_ktp'],
                    'no_rekening' => $d['no_rekening'] ?: null,
                    'project_id'  => $projectId,
                    'position_id' => $position->id,
                    'aktif'       => true,
                ]);
                $inserted++;
            } else {
                if ($employee->project_id != $projectId) {
                    $warnings[] = "KTP {$d['no_ktp']} ({$d['nama']}) sudah ada di project lain (badge {$employee->id_badge})";
                }
                $employee->update([
                    'nama_lengkap'=> $d['nama'],
                    'project_id'  => $projectId,
                    'position_id' => $position->id,
                    'no_rekening' => $d['no_rekening'] ?: $employee->no_rekening,
                ]);
                $updated++;
            }

            // 3. Hitung BPJS
            $upahPenuh       = $d['gaji_pokok'] + $d['tunj_tetap'];
            $kompensasiPwt   = round($upahPenuh / 12, 2);
            $potonganJht     = round($upahPenuh * 0.02);
            $potonganPensiun = round($upahPenuh * 0.01);
            $potonganKes     = round($upahPenuh * 0.01);

            // 4. Simpan payroll
            EmployeePayroll::updateOrCreate(
                ['employee_id' => $employee->id, 'tahun' => $TAHUN, 'bulan' => $BULAN],
                [
                    'gaji_pokok'            => $d['gaji_pokok'],
                    'tunj_tetap'            => $d['tunj_tetap'],
                    'kompensasi_pwt'        => $kompensasiPwt,
                    'kompensasi_kontrak'    => $d['kompensasi_kontrak'],
                    'tunj_makan'            => 0,
                    'tunj_produksi'         => 0,
                    'tunj_lapangan'         => 0,
                    'tunj_kehadiran'        => 0,
                    'tunj_pulsa'            => 0,
                    'insentif'              => 0,
                    'com_day'               => 0,
                    'jml_jam_lembur'        => $d['jml_jam_lembur'],
                    'upah_lembur'           => $d['upah_lembur'],
                    'l_sabtu'               => 0,
                    'l_libur'               => 0,
                    'lembur_biasa'          => 0,
                    'total_lembur_flat'     => 0,
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
                    'dibuat_oleh'           => $DIBUAT_OLEH,
                ]
            );

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Keterangan', 'Jumlah'],
            [
                ['Karyawan baru dibuat', $inserted],
                ['Karyawan diupdate',    $updated],
                ['Total payroll disimpan', count($data)],
                ['Periode', "April {$TAHUN} (bulan 4)"],
            ]
        );

        if (!empty($warnings)) {
            $this->newLine();
            $this->warn('PERHATIAN:');
            foreach ($warnings as $w) {
                $this->warn("  !  {$w}");
            }
        }

        $this->newLine();
        $this->info('Selesai! Langkah berikutnya:');
        $this->line('  1. Aktifkan TTT "Kompensasi Kontrak" di konfigurasi project NK');
        $this->line('  2. Cek karyawan Fahrizaldi & Dwi Putra (KTP asli di Excel sama)');

        return 0;
    }
}