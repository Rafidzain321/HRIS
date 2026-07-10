<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('employee_payroll', function (Blueprint $table) {
            if (!Schema::hasColumn('employee_payroll', 'ttt_perhari')) {
                $table->decimal('ttt_perhari', 15, 2)->default(0)->after('tunj_lapangan');
            }
            if (!Schema::hasColumn('employee_payroll', 'dul')) {
                $table->decimal('dul', 15, 2)->default(0)->after('ttt_perhari');
            }
            if (!Schema::hasColumn('employee_payroll', 'h_sabtu')) {
                $table->integer('h_sabtu')->default(0)->after('h_kerja');
            }
            if (!Schema::hasColumn('employee_payroll', 'h_minggu_libur')) {
                $table->integer('h_minggu_libur')->default(0)->after('h_sabtu');
            }
            if (!Schema::hasColumn('employee_payroll', 'total_jam_ot_15x')) {
                $table->decimal('total_jam_ot_15x', 10, 2)->default(0)->after('jml_jam_lembur');
            }
            if (!Schema::hasColumn('employee_payroll', 'total_jam_ot_2x')) {
                $table->decimal('total_jam_ot_2x', 10, 2)->default(0)->after('total_jam_ot_15x');
            }
        });
    }
};
