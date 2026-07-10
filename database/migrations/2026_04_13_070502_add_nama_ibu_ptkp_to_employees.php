<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('nama_ibu', 200)->nullable()->after('nama_lengkap');
            $table->string('ptkp', 10)->nullable()->after('jenis_kelamin');
            // TK/0, TK/1, TK/2, TK/3, K/0, K/1, K/2, K/3
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['nama_ibu', 'ptkp']);
        });
    }
};

