<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('employee_ho_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->unique()->constrained('employees')->onDelete('cascade');
            $table->enum('unit', ['HO-1', 'HO-2'])->nullable();
            $table->string('nik_ho', 40)->nullable();
            $table->string('lokasi_kerja')->nullable();
            $table->string('status_karyawan', 40)->nullable();
            $table->string('nama_ktp')->nullable();
            $table->string('no_kk', 40)->nullable();
            $table->string('rt_rw', 20)->nullable();
            $table->string('kelurahan')->nullable();
            $table->string('kecamatan')->nullable();
            $table->string('propinsi')->nullable();
            $table->string('npwp', 40)->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_ho_details');
    }
};
