<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('drivers_detail', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200)->index();
            $table->string('id_card', 20)->nullable();
            $table->string('badge', 30)->nullable()->index();
            $table->string('license_type', 10)->nullable();
            $table->string('license_no', 30)->nullable();
            $table->string('rfid', 20)->nullable();
            $table->date('posttest_schedule')->nullable();
            $table->string('driver_status', 100)->nullable()->index();
            $table->date('permit_expired_date')->nullable()->index();
            $table->string('posttest_schedule_status', 50)->nullable();
            $table->string('posttest_status', 20)->nullable();
            $table->date('date_approve_posttest')->nullable();
            $table->string('dvp_status', 150)->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('drivers_detail'); }
};
