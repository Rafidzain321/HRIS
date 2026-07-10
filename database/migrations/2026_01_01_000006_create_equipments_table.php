<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('equipments', function (Blueprint $table) {
            $table->id();
            $table->string('no_unit', 30)->unique();
            $table->string('plat_nomor', 20)->nullable();
            $table->string('type_unit', 100)->nullable()->comment('DUMP TRUCK, EXCAVATOR dll');
            $table->string('kapasitas', 30)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('brand', 50)->nullable()->comment('MITSUBISHI, KOMATSU dll');
            $table->string('no_rangka', 50)->nullable();
            $table->year('tahun')->nullable();
            $table->string('kategori', 50)->nullable()->comment('HEAVY VEHICLE, LIGHT VEHICLE');
            // Dokumen & Expired
            $table->date('stnk_expired')->nullable();
            $table->date('tax_expired')->nullable();
            $table->date('kir_expired')->nullable();
            $table->date('vehicle_pass_expired')->nullable();
            $table->date('izin_non_bm_expired')->nullable();
            $table->date('inspection_date')->nullable();
            // Operator assignment
            $table->unsignedBigInteger('operator_id')->nullable();
            $table->string('status', 30)->default('AKTIF');
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->foreign('operator_id')->references('id')->on('employees')->onDelete('set null');
        });
    }
    public function down(): void { Schema::dropIfExists('equipments'); }
};
