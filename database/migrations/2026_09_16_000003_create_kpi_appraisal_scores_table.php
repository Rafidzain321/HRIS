<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_appraisal_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_appraisal_id')->constrained('kpi_appraisals')->cascadeOnDelete();
            $table->foreignId('kpi_criteria_id')->constrained('kpi_criteria')->cascadeOnDelete();
            $table->unsignedTinyInteger('nilai')->nullable(); // 1-5, kosong = belum dinilai
            $table->timestamps();

            $table->unique(['kpi_appraisal_id', 'kpi_criteria_id'], 'kpi_score_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_appraisal_scores');
    }
};
