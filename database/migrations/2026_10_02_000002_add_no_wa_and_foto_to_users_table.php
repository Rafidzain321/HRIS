<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Halaman "Akun Saya": nomor WhatsApp & foto profil yang diisi user sendiri.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('no_wa', 20)->nullable()->after('email');
            $table->string('foto_path')->nullable()->after('no_wa'); // disk private (local): avatars/...
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['no_wa', 'foto_path']);
        });
    }
};
