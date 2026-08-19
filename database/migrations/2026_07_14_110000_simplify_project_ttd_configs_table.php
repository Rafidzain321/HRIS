<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// TTD (tanda tangan) tidak perlu histori per tanggal seperti BPJS — cukup 1 konfigurasi
// aktif per project (siapa yang menandatangani slip SEKARANG), diedit di tempat.
return new class extends Migration {
    public function up(): void
    {
        Schema::table('project_ttd_configs', function (Blueprint $table) {
            // FK project_id butuh index — drop dulu constraint-nya sebelum ganti unique index-nya.
            $table->dropForeign(['project_id']);
            $table->dropUnique(['project_id', 'berlaku_mulai']);
            $table->dropColumn('berlaku_mulai');
            $table->unique('project_id');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('project_ttd_configs', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropUnique(['project_id']);
            $table->date('berlaku_mulai')->nullable();
            $table->unique(['project_id', 'berlaku_mulai']);
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
        });
    }
};
