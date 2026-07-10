<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Buat tabel projects ──────────────────────────
        Schema::dropIfExists('projects'); // safety kalau sebelumnya gagal tengah jalan
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 20)->unique();
            $table->string('nama');
            $table->string('lokasi')->nullable();
            $table->enum('tipe_timesheet', ['7jam', '8jam'])->default('7jam');
            $table->enum('tipe_gaji', ['giam', 'md'])->default('giam');
            $table->string('warna', 10)->default('#3A8FE0');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ── 2. Tambah project_id ke employees ───────────────
        if (!Schema::hasColumn('employees', 'project_id')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->foreignId('project_id')
                    ->nullable()
                    ->constrained('projects')
                    ->nullOnDelete()
                    ->after('id');
            });
        }

        // ── 3. Tambah project_id ke timesheet_members ───────
        if (!Schema::hasColumn('timesheet_members', 'project_id')) {
            Schema::table('timesheet_members', function (Blueprint $table) {
                $table->foreignId('project_id')
                    ->nullable()
                    ->constrained('projects')
                    ->nullOnDelete()
                    ->after('id');
            });
        }

        // ── 4. Tambah project_id ke users ───────────────────
        // is_active TIDAK ditambah karena sudah ada di tabel users
        if (!Schema::hasColumn('users', 'project_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('project_id')
                    ->nullable()
                    ->constrained('projects')
                    ->nullOnDelete()
                    ->after('id');
            });
        }
    }

    public function down(): void
    {
        // Hapus foreign key dan kolom dari users
        if (Schema::hasColumn('users', 'project_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['project_id']);
                $table->dropColumn('project_id');
            });
        }

        // Hapus foreign key dan kolom dari timesheet_members
        if (Schema::hasColumn('timesheet_members', 'project_id')) {
            Schema::table('timesheet_members', function (Blueprint $table) {
                $table->dropForeign(['project_id']);
                $table->dropColumn('project_id');
            });
        }

        // Hapus foreign key dan kolom dari employees
        if (Schema::hasColumn('employees', 'project_id')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropForeign(['project_id']);
                $table->dropColumn('project_id');
            });
        }

        // Hapus tabel projects
        Schema::dropIfExists('projects');
    }
};