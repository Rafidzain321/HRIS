<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('project_ttd_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->date('berlaku_mulai');
            $table->json('ttd_list');
            $table->string('dibuat_oleh')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'berlaku_mulai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_ttd_configs');
    }
};
