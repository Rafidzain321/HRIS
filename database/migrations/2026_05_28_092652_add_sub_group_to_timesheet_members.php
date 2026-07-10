<?php
// database/migrations/xxxx_add_sub_group_to_timesheet_members.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timesheet_members', function (Blueprint $table) {
            // null  = tidak dikelompokkan (tampil di semua tab)
            // 'construction' = kelompok Construction
            // 'piling'       = kelompok Piling
            $table->string('sub_group', 30)->nullable()->after('tipe');
        });
    }

    public function down(): void
    {
        Schema::table('timesheet_members', function (Blueprint $table) {
            $table->dropColumn('sub_group');
        });
    }
};