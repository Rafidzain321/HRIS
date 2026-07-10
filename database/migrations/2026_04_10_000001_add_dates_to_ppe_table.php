<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ppe', function (Blueprint $table) {
            // Tanggal distribusi per item PPE
            $table->date('tgl_frc')->nullable()->after('safety_vest')->comment('Tgl distribusi FRC terakhir');
            $table->date('tgl_sepatu')->nullable()->after('tgl_frc')->comment('Tgl distribusi sepatu terakhir');
            $table->date('tgl_helm_putih')->nullable()->after('tgl_sepatu')->comment('Tgl distribusi helm putih');
            $table->date('tgl_glass')->nullable()->after('tgl_helm_putih')->comment('Tgl distribusi safety glass');
            $table->date('tgl_vest')->nullable()->after('tgl_glass')->comment('Tgl distribusi safety vest');
            $table->date('tgl_helm')->nullable()->after('tgl_vest')->comment('Tgl distribusi helm');
        });
    }

    public function down(): void
    {
        Schema::table('ppe', function (Blueprint $table) {
            $table->dropColumn(['tgl_frc','tgl_sepatu','tgl_helm_putih','tgl_glass','tgl_vest','tgl_helm']);
        });
    }
};
