<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('employee_payroll', function (Blueprint $table) {
            // Info saja (metode Netto, ditanggung perusahaan) — tidak dikurangkan dari gaji_bersih.
            $table->decimal('pph21', 15, 2)->default(0)->after('gaji_bersih');
        });
    }

    public function down(): void
    {
        Schema::table('employee_payroll', function (Blueprint $table) {
            $table->dropColumn('pph21');
        });
    }
};
