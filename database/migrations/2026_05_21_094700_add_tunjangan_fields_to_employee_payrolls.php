<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('employee_payroll', function (Blueprint $table) {
            $table->decimal('tunj_kehadiran', 12, 2)->default(0)->after('tunj_lapangan');
            $table->decimal('tunj_pulsa', 12, 2)->default(0)->after('tunj_kehadiran');
            $table->decimal('kompensasi_kontrak', 12, 2)->default(0)->after('tunj_pulsa');
        });
    }

    public function down(): void
    {
        Schema::table('employee_payroll', function (Blueprint $table) {
            $table->dropColumn(['tunj_kehadiran', 'tunj_pulsa', 'kompensasi_kontrak']);
        });
    }
};
