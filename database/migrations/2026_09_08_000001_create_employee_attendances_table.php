<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('employee_attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedSmallInteger('tahun');
            $table->unsignedTinyInteger('bulan');
            $table->unsignedTinyInteger('hadir')->default(0);
            $table->unsignedTinyInteger('izin')->default(0);
            $table->unsignedTinyInteger('sakit')->default(0);
            $table->unsignedTinyInteger('alpha')->default(0);
            // "cuti" sengaja TIDAK disimpan di sini — selalu dihitung langsung dari tabel
            // employee_leaves (Cuti Tahunan) supaya tidak dobel input dan selalu konsisten.
            $table->string('dicatat_oleh', 100)->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->unique(['employee_id', 'tahun', 'bulan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_attendances');
    }
};
