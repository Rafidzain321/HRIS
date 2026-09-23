<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // Konseling — pembinaan/percakapan suportif dengan karyawan (beda dari Surat Peringatan
    // yang sifatnya disiplin). Berlaku untuk semua karyawan di semua project, tidak dibatasi HO,
    // supaya konsisten dengan SP yang juga berlaku universal.
    public function up(): void
    {
        Schema::create('employee_counselings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('tanggal_konseling');
            // kinerja / disiplin / pribadi / karir / lainnya
            $table->string('kategori', 30);
            $table->text('catatan');
            $table->text('tindak_lanjut')->nullable();
            // selesai / perlu_tindak_lanjut
            $table->string('status', 20)->default('selesai');
            $table->string('ditangani_oleh', 100)->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->index(['employee_id', 'tanggal_konseling']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_counselings');
    }
};
