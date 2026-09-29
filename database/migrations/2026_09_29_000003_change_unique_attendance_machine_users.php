<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Lokasi tidak lagi diinput saat import (diambil otomatis dari nama file), jadi user mesin dikenali dari No. ID + nama.
    public function up(): void
    {
        Schema::table('attendance_machine_users', function (Blueprint $table) {
            $table->dropUnique(['lokasi', 'no_id']);
            $table->string('lokasi', 60)->nullable()->change();
            $table->unique(['no_id', 'nama_mesin']);
        });
    }

    public function down(): void
    {
        Schema::table('attendance_machine_users', function (Blueprint $table) {
            $table->dropUnique(['no_id', 'nama_mesin']);
            $table->string('lokasi', 60)->nullable(false)->change();
            $table->unique(['lokasi', 'no_id']);
        });
    }
};
