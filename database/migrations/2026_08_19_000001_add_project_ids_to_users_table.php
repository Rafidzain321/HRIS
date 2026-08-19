<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Kolom ini sebelumnya sudah ada di database development (ditambahkan manual, tanpa
    // migration tercatat) — migration ini dibuat supaya production punya kolom yang sama
    // secara resmi/tercatat. Pakai hasColumn guard supaya aman dijalankan di environment
    // manapun, baik yang kolomnya sudah ada maupun yang belum.
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'project_ids')) {
            Schema::table('users', function (Blueprint $table) {
                $table->longText('project_ids')->nullable()->after('project_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'project_ids')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('project_ids');
            });
        }
    }
};
