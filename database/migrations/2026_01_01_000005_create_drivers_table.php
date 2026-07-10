<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->unique();
            $table->string('license_type', 20)->nullable()->comment('B2, B1 dll');
            $table->string('license_no', 50)->nullable();
            $table->string('rfid', 50)->nullable();
            $table->date('permit_expired_date')->nullable();
            $table->date('posttest_schedule')->nullable();
            $table->string('driver_status', 50)->nullable();
            $table->string('posttest_schedule_status', 50)->nullable();
            $table->string('posttest_status', 50)->nullable()->comment('PASS / FAIL');
            $table->date('date_approve_posttest')->nullable();
            $table->string('dvp_status', 50)->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
        });
    }
    public function down(): void { Schema::dropIfExists('drivers'); }
};
