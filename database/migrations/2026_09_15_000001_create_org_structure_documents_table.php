<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Cuma dipakai buat satu dokumen "terkini" (bukan riwayat per-versi) — bagan/struktur
    // organisasi resmi yang di-upload HR sebagai referensi visual, terpisah dari data
    // atasan-bawahan fungsional di kolom employees.atasan_id.
    public function up(): void
    {
        Schema::create('org_structure_documents', function (Blueprint $table) {
            $table->id();
            $table->string('nama_file');
            $table->string('path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_structure_documents');
    }
};
