<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // SIM - kota dikeluarkan
            $table->string('sim_kota_keluar', 100)->nullable()->after('no_sim');
            // Foto profil karyawan
            $table->string('foto_profil', 500)->nullable()->after('foto');
        });
    }
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['sim_kota_keluar', 'foto_profil']);
        });
    }
};
 