<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // PKWT / PKWTT / PROBATION — untuk karyawan kantor lapangan (HO pakai employee_ho_details.status_karyawan).
            $table->string('status_kerja', 20)->nullable()->after('tanggal_masuk');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('status_kerja');
        });
    }
};
