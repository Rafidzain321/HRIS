<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // User multi-project biasanya cuma full-edit di 1 project utama (project_id), sisanya
    // (project_ids) otomatis read-only. Kolom ini daftar project TAMBAHAN di luar project_id
    // yang juga boleh di-edit penuh (bukan cuma dilihat) — dipakai user yang memang bertanggung
    // jawab mengelola lebih dari satu project sekaligus.
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'full_edit_project_ids')) {
            Schema::table('users', function (Blueprint $table) {
                $table->longText('full_edit_project_ids')->nullable()->after('project_ids');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('full_edit_project_ids');
        });
    }
};
