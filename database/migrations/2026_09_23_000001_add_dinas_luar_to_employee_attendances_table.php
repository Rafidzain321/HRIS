<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // Dinas Luar (tugas ke luar kantor) dihitung sebagai "Masuk" di rekap kehadiran, tapi
    // dicatat di kolom terpisah dari "hadir" biar tetap bisa dibedakan/dilaporkan.
    public function up(): void
    {
        Schema::table('employee_attendances', function (Blueprint $table) {
            $table->unsignedTinyInteger('dinas_luar')->default(0)->after('hadir');
        });
    }

    public function down(): void
    {
        Schema::table('employee_attendances', function (Blueprint $table) {
            $table->dropColumn('dinas_luar');
        });
    }
};
