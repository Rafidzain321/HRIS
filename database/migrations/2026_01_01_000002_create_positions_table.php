<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('nama_jabatan', 150);
            $table->string('level', 50)->nullable()->comment('staff, supervisor, manager, operator');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->decimal('gaji_pokok_min', 14, 2)->default(0);
            $table->decimal('gaji_pokok_max', 14, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
        });
    }
    public function down(): void { Schema::dropIfExists('positions'); }
};
