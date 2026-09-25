<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // Sub-alasan khusus buat jenis 'izin_tanpa_potong' (Pasal 26 PP — menikah, keluarga
    // meninggal, dst) supaya bisa dilaporkan per kategori, bukan cuma teks bebas.
    public function up(): void
    {
        Schema::table('employee_leaves', function (Blueprint $table) {
            $table->string('kategori_izin', 40)->nullable()->after('jenis');
        });
    }

    public function down(): void
    {
        Schema::table('employee_leaves', function (Blueprint $table) {
            $table->dropColumn('kategori_izin');
        });
    }
};
