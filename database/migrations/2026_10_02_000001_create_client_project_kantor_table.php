<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Satu project (client_projects) sekarang boleh terhubung ke lebih dari satu kantor (projects):
// client_projects.project_id diganti tabel pivot client_project_kantor. Hubungan lama ikut disalin.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_project_kantor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_project_id')->constrained('client_projects')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['client_project_id', 'project_id']);
        });

        $now = now();
        DB::table('client_projects')->whereNotNull('project_id')->orderBy('id')->get(['id', 'project_id'])
            ->each(fn($cp) => DB::table('client_project_kantor')->insert([
                'client_project_id' => $cp->id, 'project_id' => $cp->project_id, 'created_at' => $now, 'updated_at' => $now,
            ]));

        Schema::table('client_projects', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropColumn('project_id');
        });
    }

    public function down(): void
    {
        Schema::table('client_projects', function (Blueprint $table) {
            $table->unsignedBigInteger('project_id')->nullable()->after('kode');
            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
        });

        // Kolom lama cuma muat satu kantor — ambil kantor pertama tiap project.
        DB::table('client_project_kantor')->orderBy('id')->get()->groupBy('client_project_id')
            ->each(fn($rows, $cpId) => DB::table('client_projects')->where('id', $cpId)->update(['project_id' => $rows->first()->project_id]));

        Schema::dropIfExists('client_project_kantor');
    }
};
