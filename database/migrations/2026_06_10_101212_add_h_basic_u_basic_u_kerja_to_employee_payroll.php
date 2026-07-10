<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('employee_payroll', function (Blueprint $table) {
            if (!Schema::hasColumn('employee_payroll', 'h_basic')) {
                $table->integer('h_basic')->default(0)->after('h_kerja');
            }
            if (!Schema::hasColumn('employee_payroll', 'u_basic')) {
                $table->decimal('u_basic', 15, 2)->default(0)->after('h_basic');
            }
            if (!Schema::hasColumn('employee_payroll', 'u_kerja')) {
                $table->decimal('u_kerja', 15, 2)->default(0)->after('u_basic');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employee_payroll', function (Blueprint $table) {
            $table->dropColumn(['h_basic', 'u_basic', 'u_kerja']);
        });
    }
};
