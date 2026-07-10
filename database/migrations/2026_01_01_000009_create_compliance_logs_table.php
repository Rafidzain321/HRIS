<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('compliance_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('tipe', 50)->comment('SIM, SIO, MCU, BADGE, KP, PKWT');
            $table->string('status_lama', 100)->nullable();
            $table->string('status_baru', 100)->nullable();
            $table->date('expired_lama')->nullable();
            $table->date('expired_baru')->nullable();
            $table->text('catatan')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->index(['employee_id', 'tipe']);
        });
    }
    public function down(): void { Schema::dropIfExists('compliance_logs'); }
};
