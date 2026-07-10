<?php
// database/migrations/xxxx_create_training_tables.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Master jenis training
        Schema::create('training_types', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 150)->unique();
            $table->text('deskripsi')->nullable();
            // Masa berlaku: null = seumur hidup, 1-5 = tahun
            $table->unsignedTinyInteger('masa_berlaku_tahun')->nullable()
                ->comment('null=seumur hidup, 1-5=tahun');
            $table->boolean('has_nilai')->default(false);   // ada nilai post test
            $table->boolean('has_expired')->default(false); // ada tanggal expired
            $table->boolean('is_active')->default(true);
            $table->integer('urutan')->default(0);          // urutan tampil
            $table->timestamps();
        });

        // Data training per karyawan
        Schema::create('employee_trainings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_type_id')->constrained()->cascadeOnDelete();
            $table->date('tanggal')->nullable();         // tanggal training
            $table->string('nama_trainer', 150)->nullable();
            $table->string('nilai', 20)->nullable();     // nilai post test (93, 87, dll)
            $table->string('status', 20)->nullable();    // PASS / FAIL / HADIR
            $table->date('expired_date')->nullable();    // tanggal kadaluarsa (auto atau manual)
            $table->text('catatan')->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Satu karyawan tidak bisa punya 2x training type yang sama
            $table->unique(['employee_id', 'training_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_trainings');
        Schema::dropIfExists('training_types');
    }
};
