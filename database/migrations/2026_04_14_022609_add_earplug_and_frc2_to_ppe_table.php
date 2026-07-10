<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ppe', function (Blueprint $table) {
            // FRC pengambilan ke-2
            $table->date('tgl_frc_2')->nullable()->after('tgl_frc');
            // Ear Plug
            $table->boolean('ear_plug')->default(false)->after('safety_vest');
            $table->date('tgl_ear_plug')->nullable()->after('ear_plug');
        });
    }

    public function down(): void
    {
        Schema::table('ppe', function (Blueprint $table) {
            $table->dropColumn(['tgl_frc_2', 'ear_plug', 'tgl_ear_plug']);
        });
    }
};
