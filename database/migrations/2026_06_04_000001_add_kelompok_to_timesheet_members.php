<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('timesheet_members', function (Blueprint $table) {
            $table->string('kelompok', 20)->default('per_jam')->after('tipe');
            // nilai: 'per_jam' atau 'flat'
        });
    }
    public function down(): void {
        Schema::table('timesheet_members', function (Blueprint $table) {
            $table->dropColumn('kelompok');
        });
    }
};