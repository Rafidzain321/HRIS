<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Struktur atasan-bawahan (dari bagan organisasi HO) — dipakai buat dashboard KPI "tim saya"
    // per manajer nantinya.
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('atasan_id')->nullable()->after('position_id')
                ->constrained('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('atasan_id');
        });
    }
};
