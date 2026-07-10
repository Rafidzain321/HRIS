<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timesheet_members', function (Blueprint $table) {
            // Tipe timesheet per member (override dari project)
            $table->enum('tipe', ['7jam', '8jam'])->default('7jam')->after('aktif');
        });
    }

    public function down(): void
    {
        Schema::table('timesheet_members', function (Blueprint $table) {
            $table->dropColumn('tipe');
        });
    }
};