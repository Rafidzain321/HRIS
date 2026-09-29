<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // User di mesin fingerprint (No. ID per lokasi mesin) -> dipetakan ke karyawan HRIS.
        Schema::create('attendance_machine_users', function (Blueprint $table) {
            $table->id();
            $table->string('lokasi', 60);          // judul lokasi di sheet, mis. "AKM -TUNAS JAYA"
            $table->string('no_id', 20);
            $table->string('nama_mesin', 100);
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->timestamps();
            $table->unique(['lokasi', 'no_id']);
            $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();
        });

        // Data scan harian hasil import. kategori/keterangan = input manual HR (tidak ditimpa saat import ulang).
        Schema::create('attendance_scans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('machine_user_id');
            $table->date('tanggal');
            $table->string('jam_kerja', 30)->nullable();
            $table->string('scan_masuk', 5)->nullable();
            $table->string('scan_pulang', 5)->nullable();
            $table->string('pengecualian', 100)->nullable();
            $table->string('waktu_scan', 100)->nullable();
            $table->string('kategori', 20)->nullable();
            $table->string('keterangan', 150)->nullable();
            $table->timestamps();
            $table->unique(['machine_user_id', 'tanggal']);
            $table->foreign('machine_user_id')->references('id')->on('attendance_machine_users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_scans');
        Schema::dropIfExists('attendance_machine_users');
    }
};
