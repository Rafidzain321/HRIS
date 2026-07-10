<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('ccpm_manpower', function (Blueprint $table) {
            $table->id();
            $table->string('badge', 30)->nullable()->index();
            $table->string('id_card', 20)->nullable();
            $table->string('hes_passport', 50)->nullable();
            $table->string('name', 200);
            $table->string('birth_place', 100)->nullable();
            $table->date('birth_date')->nullable();
            $table->date('ffd_valid_date')->nullable();
            $table->date('badge_valid_date')->nullable();
            $table->string('job_title', 150)->nullable()->index();
            $table->string('team_assignment', 100)->nullable();
            $table->string('status', 100)->nullable()->index();
            $table->string('status_medical', 100)->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('ccpm_manpower'); }
};
