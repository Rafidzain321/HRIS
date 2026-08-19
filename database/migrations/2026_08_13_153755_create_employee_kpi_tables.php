<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_kpi_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('nama_indikator', 255);
            $table->decimal('bobot', 5, 2);
            $table->unsignedInteger('urutan')->default(0);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('employee_kpi_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indicator_id')->constrained('employee_kpi_indicators')->cascadeOnDelete();
            $table->unsignedSmallInteger('tahun');
            $table->unsignedTinyInteger('semester'); // 1 atau 2
            $table->decimal('skor', 5, 2); // 0-100
            $table->text('catatan')->nullable();
            $table->string('dibuat_oleh', 100)->nullable();
            $table->timestamps();

            $table->unique(['indicator_id', 'tahun', 'semester']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_kpi_scores');
        Schema::dropIfExists('employee_kpi_indicators');
    }
};
