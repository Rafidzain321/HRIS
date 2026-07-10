<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('trainings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('jenis_training', 100)->comment('HI, SWP PTW HA, MVSHE, FFD, dll');
            $table->date('tanggal_training')->nullable();
            $table->date('tanggal_expired')->nullable();
            $table->string('trainer', 150)->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->string('status', 20)->nullable()->comment('PASS / FAIL');
            $table->string('lokasi', 150)->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->index(['employee_id', 'jenis_training']);
        });
    }
    public function down(): void { Schema::dropIfExists('trainings'); }
};
