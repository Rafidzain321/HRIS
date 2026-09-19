<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Satu baris = satu form penilaian karyawan untuk satu semester (menggantikan konsep
    // "Goal" bebas — sekarang penilaiannya standar 20 poin per form, lihat kpi_criteria).
    public function up(): void
    {
        Schema::create('kpi_appraisals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            // Penilai — bebas ditugaskan siapa saja, sama seperti reviewer_id di sistem goal
            // sebelumnya; kalau kosong berarti belum ditugaskan / default ke atasan langsung.
            $table->foreignId('reviewer_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->unsignedSmallInteger('tahun');
            $table->unsignedTinyInteger('semester'); // 1 atau 2
            $table->decimal('nilai_a', 5, 2)->nullable();   // hasil (40/maks_A) x jumlah_nilai_A
            $table->decimal('nilai_b', 5, 2)->nullable();   // hasil (60/maks_B) x jumlah_nilai_B
            $table->decimal('total_nilai', 5, 2)->nullable(); // nilai_a + nilai_b, skala 0-100
            $table->string('predikat', 5)->nullable(); // K / C / B / BS / A
            $table->text('catatan')->nullable();
            $table->enum('status', ['draft', 'submitted'])->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'tahun', 'semester']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_appraisals');
    }
};
