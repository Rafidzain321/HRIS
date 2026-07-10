<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('group')->nullable()->change();
            $table->string('sio_k3')->nullable()->change();
            $table->string('ccpm')->nullable()->change();
            $table->string('rfid')->nullable()->change();
            $table->string('status_kp')->nullable()->change();
            $table->string('type_sim')->nullable()->change();
            $table->string('no_sim')->nullable()->change();
            $table->string('sim_kota_keluar')->nullable()->change();
            $table->string('no_sio')->nullable()->change();
            $table->string('tipe_sio')->nullable()->change();
            $table->string('nama_perusahaan_sio')->nullable()->change();
            $table->string('status_mcu')->nullable()->change();
            $table->string('lokasi_mcu')->nullable()->change();
            $table->string('derajat_kesehatan')->nullable()->change();
            $table->string('ukuran_baju')->nullable()->change();
            $table->string('ukuran_sepatu')->nullable()->change();
            $table->string('no_contract')->nullable()->change();
            $table->string('ptkp')->nullable()->change();
            $table->string('tamatan')->nullable()->change();
            $table->string('agama')->nullable()->change();
            $table->string('kota_asal')->nullable()->change();
            $table->string('alamat')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Tidak perlu rollback untuk kasus ini
    }
};