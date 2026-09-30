<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Jam masuk/pulang hasil edit manual (khusus Sabtu — absen bisa di kajian/kantor). Disimpan terpisah
    // dari scan mesin supaya tidak tertimpa saat import ulang; yang dipakai: manual kalau ada, kalau tidak mesin.
    public function up(): void
    {
        Schema::table('attendance_scans', function (Blueprint $table) {
            $table->string('scan_masuk_manual', 5)->nullable()->after('scan_pulang');
            $table->string('scan_pulang_manual', 5)->nullable()->after('scan_masuk_manual');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_scans', function (Blueprint $table) {
            $table->dropColumn(['scan_masuk_manual', 'scan_pulang_manual']);
        });
    }
};
