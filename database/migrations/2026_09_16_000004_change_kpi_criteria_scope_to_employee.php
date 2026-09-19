<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Kriteria tambahan awalnya di-scope per departemen — diganti jadi per-karyawan
    // langsung, karena kebutuhannya ternyata beda-beda tiap orang (bukan per jabatan/departemen).
    public function up(): void
    {
        Schema::table('kpi_criteria', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn('department_id');
            $table->unsignedBigInteger('employee_id')->nullable()->after('deskripsi');
            $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('kpi_criteria', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropColumn('employee_id');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
        });
    }
};
