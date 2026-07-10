<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->date('tanggal_keluar')->nullable()->after('end_pkwt');
            $table->string('alasan_keluar', 50)->nullable()->after('tanggal_keluar');
            // RESIGN, PHK, KONTRAK HABIS, MENINGGAL, MUTASI, LAINNYA
            $table->text('catatan_keluar')->nullable()->after('alasan_keluar');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['tanggal_keluar', 'alasan_keluar', 'catatan_keluar']);
        });
    }
};
