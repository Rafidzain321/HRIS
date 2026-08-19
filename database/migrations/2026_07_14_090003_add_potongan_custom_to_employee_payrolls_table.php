<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('employee_payroll', function (Blueprint $table) {
            $table->json('potongan_custom')->nullable()->after('ttt_custom');
        });
    }

    public function down(): void
    {
        Schema::table('employee_payroll', function (Blueprint $table) {
            $table->dropColumn('potongan_custom');
        });
    }
};
