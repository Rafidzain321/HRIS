<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('equipment_operators', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('equipment_id');
            // ── Link ke Employee (opsional) ──
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->string('operator_name', 200)->nullable();
            $table->string('badge', 30)->nullable();
            // ── GOI Operator / Driver ──
            $table->string('license_no', 50)->nullable();
            $table->date('license_expired_date')->nullable();
            $table->string('rfid', 20)->nullable();
            $table->string('kp_no', 100)->nullable();
            $table->date('kp_expired_date')->nullable();
            $table->date('cdrive_expired_date')->nullable()->comment('C-DRIVE EXPIRED DATE');
            $table->date('postest_expired_date')->nullable()->comment('POSTEST EXPIRED DATE');
            $table->string('permit_no', 100)->nullable();
            $table->date('permit_expired_date')->nullable();
            $table->string('sio_migas_no', 100)->nullable();
            $table->date('sio_migas_expired')->nullable();
            $table->date('sio_disnaker_expired')->nullable();
            $table->string('k3_p3a2_no', 150)->nullable();
            $table->date('k3_p3a2_expired')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('equipment_id')->references('id')->on('equipments')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('set null');
            $table->index('equipment_id');
            $table->index('badge');
        });
    }

    public function down(): void {
        Schema::dropIfExists('equipment_operators');
    }
};
