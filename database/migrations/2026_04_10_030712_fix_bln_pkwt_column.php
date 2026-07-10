<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Ubah bln_pkwt dari integer ke string (nilai romawi: X, XII, dll)
            $table->string('bln_pkwt', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->integer('bln_pkwt')->nullable()->change();
        });
    }
};
