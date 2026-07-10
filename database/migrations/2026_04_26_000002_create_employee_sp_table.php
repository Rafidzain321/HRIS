<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('employee_sp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->enum('tipe_sp', ['SP1','SP2','SP3','SKORSING','PHK']);
            $table->date('tanggal_sp');
            $table->text('alasan');
            $table->text('catatan')->nullable();
            $table->string('dibuat_oleh')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('employee_sp'); }
};