<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('timesheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->year('tahun');
            $table->tinyInteger('bulan'); // 1-12
            $table->tinyInteger('hari');  // 1-31
            // nilai: angka jam kerja, atau 'H'=hadir, 'I'=izin, 'S'=sakit, 'A'=alpha, 'C'=cuti, null=libur/tidak masuk
            $table->string('nilai', 10)->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'tahun', 'bulan', 'hari']);
            $table->index(['tahun', 'bulan']);
            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timesheets');
    }
};
