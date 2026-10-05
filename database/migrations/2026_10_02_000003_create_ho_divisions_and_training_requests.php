<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

// Pengajuan Training (khusus HO): master divisi HO, divisi per karyawan HO, pengajuan + peserta,
// dan permission menu baru (diberikan ke user yang sudah memegang menu Konseling — grup HR HO).
return new class extends Migration
{
    const DIVISI = [
        'Projek', 'Procurement', 'Pengendalian Internal', 'BoD Department', 'Finance Department',
        'Accounting & Tax Departement', 'Direksi', 'GA Department', 'IT Department', 'HR Department',
    ];

    public function up(): void
    {
        Schema::create('ho_divisions', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        $now = now();
        DB::table('ho_divisions')->insert(array_map(fn($n) => ['nama' => $n, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now], self::DIVISI));

        Schema::table('employee_ho_details', function (Blueprint $table) {
            $table->foreignId('ho_division_id')->nullable()->after('unit')->constrained('ho_divisions')->nullOnDelete();
        });

        Schema::create('training_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ho_division_id')->constrained('ho_divisions');
            $table->unsignedSmallInteger('tahun')->index();      // tahun kuota (dari tanggal rencana)
            $table->string('nama_training', 200);
            $table->string('penyelenggara', 200)->nullable();   // tempat / lembaga penyelenggara
            $table->date('tanggal_rencana');
            $table->unsignedBigInteger('estimasi_biaya')->nullable();
            $table->text('alasan');
            $table->string('status', 20)->default('diajukan')->index(); // diajukan, disetujui, ditolak, selesai
            $table->text('catatan_hr')->nullable();
            $table->foreignId('diajukan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('diproses_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diproses_at')->nullable();
            $table->timestamps();
        });

        Schema::create('training_request_employee', function (Blueprint $table) {
            $table->foreignId('training_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->primary(['training_request_id', 'employee_id']);
        });

        $view = Permission::firstOrCreate(['name' => 'view-pengajuan-training', 'guard_name' => 'web']);
        $edit = Permission::firstOrCreate(['name' => 'edit-pengajuan-training', 'guard_name' => 'web']);
        foreach (\App\Models\User::permission('view-konseling')->get() as $u) $u->givePermissionTo($view);
        foreach (\App\Models\User::permission('edit-konseling')->get() as $u) $u->givePermissionTo($edit);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', ['view-pengajuan-training', 'edit-pengajuan-training'])->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Schema::dropIfExists('training_request_employee');
        Schema::dropIfExists('training_requests');
        Schema::table('employee_ho_details', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ho_division_id');
        });
        Schema::dropIfExists('ho_divisions');
    }
};
