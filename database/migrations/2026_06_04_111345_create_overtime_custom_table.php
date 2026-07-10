<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('overtime_custom', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->unsignedSmallInteger('tahun');
            $table->unsignedTinyInteger('bulan');
            $table->string('kategori', 20); // 'sabtu' atau 'minggu'
            $table->unsignedInteger('tarif_per_hari')->default(0);
            $table->unsignedTinyInteger('jumlah_hari')->default(0);
            $table->timestamps();
            $table->unique(['employee_id', 'project_id', 'tahun', 'bulan', 'kategori'], 'ot_custom_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_custom');
    }
};
