<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Password tidak boleh disimpan dalam bentuk teks polos. Login tetap pakai kolom `password` (hash).
    public function up(): void
    {
        if (Schema::hasColumn('users', 'plain_password')) {
            Schema::table('users', fn(Blueprint $table) => $table->dropColumn('plain_password'));
        }
    }

    public function down(): void
    {
        Schema::table('users', fn(Blueprint $table) => $table->string('plain_password')->nullable());
    }
};
