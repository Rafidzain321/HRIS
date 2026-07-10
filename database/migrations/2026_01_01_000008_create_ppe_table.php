<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('ppe', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->unique();
            $table->string('frc', 10)->nullable()->comment('Ukuran baju FRC');
            $table->string('safety_shoes', 10)->nullable()->comment('Ukuran sepatu');
            $table->boolean('white_helmet')->default(false);
            $table->boolean('helmet')->default(false);
            $table->boolean('safety_glass')->default(false);
            $table->boolean('safety_vest')->default(false);
            $table->date('tanggal_distribusi')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
        });
    }
    public function down(): void { Schema::dropIfExists('ppe'); }
};
