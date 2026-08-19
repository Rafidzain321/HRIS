<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('project_bpjs_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->date('berlaku_mulai');
            $table->decimal('pct_jht', 5, 2)->default(2.00);
            $table->decimal('pct_pensiun', 5, 2)->default(1.00);
            $table->decimal('pct_kes', 5, 2)->default(1.00);
            $table->string('dibuat_oleh')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'berlaku_mulai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_bpjs_configs');
    }
};
