<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::dropIfExists('equipments');
        Schema::create('equipments', function (Blueprint $table) {
            $table->id();
            // ── Identitas Unit ──
            $table->string('no_unit', 30)->unique()->comment('AKM-DT-061');
            $table->string('plat_nomor', 20)->nullable()->comment('EQUIPMENT POLICE NO');
            $table->string('type_unit', 100)->nullable()->comment('DUMP TRUCK, EXCAVATOR TRACK, dll');
            $table->string('model', 100)->nullable();
            $table->string('manufacture', 50)->nullable()->comment('MITSUBISHI, CATERPILLAR, dll');
            $table->string('serial_no', 50)->nullable()->comment('No. Rangka / Serial No');
            $table->year('tahun')->nullable();
            $table->string('gps_unit_id', 50)->nullable();
            $table->string('kategori', 50)->nullable()->comment('HEAVY VEHICLE, LIGHT VEHICLE');
            $table->string('kapasitas', 30)->nullable();
            // ── GOI Equipment & Vehicle ──
            $table->date('stnk_expired')->nullable();
            $table->date('tax_expired')->nullable()->comment('TAX/PAJAK');
            $table->date('kir_expired')->nullable();
            $table->date('izin_non_bm_expired')->nullable()->comment('IZIN NON BM');
            $table->date('vehicle_pass_expired')->nullable()->comment('VEHICLE PASS');
            $table->date('inspection_date')->nullable()->comment('DATE OF INSPECTION');
            $table->date('smbr_pass_expired')->nullable()->comment('DATE EXPIRED SMBR PASS x 6 BULAN');
            $table->date('green_stiker_expired')->nullable()->comment('DATE EXPIRED GREEN STIKER PHR');
            // ── SIO / K3 ──
            $table->string('sio_migas_no', 100)->nullable()->comment('SIO MIGAS / K3 SIO DISNAKER No.');
            $table->date('sio_migas_expired')->nullable();
            $table->date('sio_disnaker_expired')->nullable()->comment('SIO MIGAS / K3 SIO DISNAKER No. EXPIRED DATE');
            $table->string('k3_p3a2_no', 150)->nullable()->comment('K3 SILO P3A2 No.');
            $table->date('k3_p3a2_expired')->nullable()->comment('K3 SILO P3A2 EXPIRED DATE');
            // ── Inspeksi ──
            $table->string('tpe_cem_inspector', 100)->nullable();
            $table->string('contractor_cem_inspector', 100)->nullable();
            $table->string('location_of_inspection', 100)->nullable()->comment('BANGKO, dll');
            // ── Status ──
            $table->string('status', 30)->default('AKTIF')->comment('AKTIF, NONAKTIF, NO COMPLY');
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->index('type_unit');
            $table->index('status');
            $table->index('stnk_expired');
            $table->index('kir_expired');
        });
    }

    public function down(): void {
        Schema::dropIfExists('equipments');
    }
};
