<?php
// database/migrations/xxxx_add_no_bpjs_to_employees_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('no_bpjs', 30)->nullable()->after('no_ktp');
        });
    }
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('no_bpjs');
        });
    }
};
