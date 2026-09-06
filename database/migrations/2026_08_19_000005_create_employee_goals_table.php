<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Menggantikan employee_kpi_indicators/employee_kpi_scores — model "Goal" ala Mekari Talenta:
    // tiap goal berdiri sendiri dengan periode & satuan ukur sendiri, progress dari baseline ke
    // target. "bobot" tetap dipertahankan (tidak ada di Talenta) supaya masih bisa dihitung 1 skor
    // akhir gabungan per karyawan per periode, sesuai kebutuhan penilaian kinerja di sini.
    public function up(): void
    {
        Schema::create('employee_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('nama_goal', 255);
            $table->text('deskripsi')->nullable();
            $table->enum('siklus', ['custom', 'monthly', 'quarterly', 'half_yearly', 'yearly'])->default('custom');
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->enum('satuan', ['percentage', 'number', 'rupiah'])->default('percentage');
            $table->decimal('baseline', 15, 2)->default(0);
            $table->decimal('target', 15, 2)->default(100);
            $table->decimal('progress_sekarang', 15, 2)->default(0);
            $table->decimal('bobot', 5, 2)->default(0);
            $table->text('catatan')->nullable();
            $table->string('diperbarui_oleh', 100)->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_goals');
    }
};
