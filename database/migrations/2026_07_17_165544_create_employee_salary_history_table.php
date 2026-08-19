<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employee_salary_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            // Label asli dari kolom sumber Excel (mis. "GAJI BRUTTO NOPEMBER 2018 ( SBLM KARYAWAN BARU )")
            // — disimpan apa adanya karena penamaannya tidak konsisten antar karyawan/tahun.
            $table->string('label', 150);
            $table->decimal('nominal', 14, 2);
            // tahun/bulan null kalau tidak bisa dipastikan dari label-nya (banyak yang ambigu).
            $table->unsignedSmallInteger('tahun')->nullable();
            $table->unsignedTinyInteger('bulan')->nullable();
            // urutan asli di sumber data — dipakai buat urutkan tampilan kalau tahun/bulan-nya kosong.
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->timestamps();

            $table->unique(['employee_id', 'label']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_salary_history');
    }
};
