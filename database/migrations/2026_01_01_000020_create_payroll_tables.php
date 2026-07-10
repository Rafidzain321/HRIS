<?php
// File: database/migrations/2026_01_01_000020_create_payroll_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Tabel konfigurasi per jabatan (kelompok 1 vs kelompok 2)
        Schema::create('payroll_jabatan_config', function (Blueprint $table) {
            $table->id();
            $table->string('nama_jabatan', 150)->unique();
            $table->enum('kelompok', ['per_jam', 'flat'])->default('per_jam');
            // Kelompok flat: tarif lembur
            $table->integer('tarif_sabtu')->default(75000);
            $table->integer('tarif_libur')->default(200000);
            $table->integer('tarif_biasa')->default(20000);
            $table->timestamps();
        });

        // Tabel data gaji per periode per karyawan
        Schema::create('employee_payroll', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->integer('tahun');
            $table->integer('bulan');

            // Komponen gaji
            $table->decimal('gaji_pokok', 15, 2)->default(0);
            $table->decimal('tunj_tetap', 15, 2)->default(0);      // Tunjangan transport/jabatan
            $table->decimal('kompensasi_pwt', 15, 2)->default(0);  // = (gapok+tt)/12
            $table->decimal('ttt_perhari', 15, 2)->default(0);     // Tunjangan Tidak Tetap per hari
            $table->decimal('com_day', 15, 2)->default(0);
            $table->decimal('insentif', 15, 2)->default(0);
            $table->decimal('tunj_makan', 15, 2)->default(0);
            $table->decimal('tunj_produksi', 15, 2)->default(0);
            $table->decimal('tunj_lapangan', 15, 2)->default(0);

            // Lembur
            $table->decimal('jml_jam_lembur', 10, 2)->default(0);  // Total jam lembur (kelompok 1)
            $table->decimal('upah_lembur', 15, 2)->default(0);     // Nominal lembur

            // Khusus kelompok 2 (flat)
            $table->integer('l_sabtu')->default(0);         // Jumlah Sabtu kerja
            $table->integer('l_libur')->default(0);         // Jumlah Minggu/libur kerja
            $table->integer('lembur_biasa')->default(0);    // Input manual
            $table->decimal('total_lembur_flat', 15, 2)->default(0);

            // Uang kehadiran (kelompok 2)
            $table->decimal('uang_hadir', 15, 2)->default(0);

            // Hasil
            $table->integer('h_kerja')->default(0);         // Hari kerja aktual
            $table->decimal('gaji_kotor', 15, 2)->default(0);

            // Potongan
            $table->decimal('potongan_jht', 15, 2)->default(0);     // BPJS TK JHT 2%
            $table->decimal('potongan_pensiun', 15, 2)->default(0); // BPJS TK Pensiun 1%
            $table->decimal('potongan_kes', 15, 2)->default(0);     // BPJS Kesehatan 1%
            $table->decimal('potongan_alpa', 15, 2)->default(0);    // Alpa/pinjaman/prorata

            // Kekurangan bulan lalu
            $table->decimal('kekurangan_bulan_lalu', 15, 2)->default(0);

            $table->decimal('gaji_bersih', 15, 2)->default(0);

            // Absensi (dari timesheet)
            $table->integer('izin')->default(0);
            $table->integer('sakit')->default(0);
            $table->integer('alpa')->default(0);
            $table->integer('cuti')->default(0);

            // Info tambahan
            $table->string('nama_bank', 50)->nullable();
            $table->string('no_rekening', 50)->nullable();
            $table->string('ptkp', 10)->nullable();
            $table->string('no_bpjs_tk', 50)->nullable();
            $table->string('no_bpjs_kes', 50)->nullable();
            $table->string('dibuat_oleh', 100)->nullable();
            $table->text('catatan')->nullable();

            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->unique(['employee_id', 'tahun', 'bulan']);
            $table->index(['tahun', 'bulan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_payroll');
        Schema::dropIfExists('payroll_jabatan_config');
    }
};