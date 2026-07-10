<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('no_contract', 100)->nullable();
            $table->tinyInteger('bulan');
            $table->year('tahun');
            $table->string('project', 100)->nullable();
            // Pendapatan
            $table->decimal('gapok', 14, 2)->default(0)->comment('Gaji Pokok');
            $table->decimal('t_jabatan', 14, 2)->default(0)->comment('Tunjangan Jabatan');
            $table->decimal('incentive', 14, 2)->default(0);
            $table->decimal('uang_makan', 14, 2)->default(0);
            $table->decimal('produksi', 14, 2)->default(0);
            $table->decimal('lapangan', 14, 2)->default(0);
            $table->decimal('lembur_sabtu', 14, 2)->default(0);
            $table->decimal('lembur_minggu', 14, 2)->default(0);
            $table->decimal('uang_transport', 14, 2)->default(0);
            $table->decimal('kompensasi', 14, 2)->default(0);
            $table->decimal('total_pendapatan', 14, 2)->default(0);
            // Status
            $table->enum('status', ['draft', 'diproses', 'dibayar'])->default('draft');
            $table->date('tanggal_bayar')->nullable();
            $table->unsignedBigInteger('dibuat_oleh')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->unique(['employee_id', 'bulan', 'tahun']);
        });
    }
    public function down(): void { Schema::dropIfExists('payrolls'); }
};
