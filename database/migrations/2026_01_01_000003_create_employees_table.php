<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            // ── Identitas ──
            $table->string('id_badge', 30)->unique()->comment('AKM-EW-0001');
            $table->string('nama_lengkap', 200);
            $table->string('no_ktp', 20)->nullable()->unique();
            $table->string('no_telepon', 25)->nullable();
            $table->string('tempat_lahir', 100)->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->text('alamat')->nullable();
            $table->string('kota_asal', 100)->nullable();
            $table->string('agama', 30)->nullable();
            $table->string('tamatan', 20)->nullable()->comment('SD, SMP, SMA, D3, S1, S2');
            $table->enum('jenis_kelamin', ['L', 'P'])->default('L');
            // ── Pekerjaan ──
            $table->unsignedBigInteger('position_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('group', 20)->default('AKM');
            $table->enum('status', ['AKTIF', 'NONAKTIF', 'RESIGN'])->default('AKTIF');
            $table->string('rfid', 50)->nullable();
            $table->string('foto', 255)->nullable();
            // ── Compliance: Badge ──
            $table->date('expire_badge')->nullable();
            $table->string('status_kp', 100)->nullable()->comment('KP has been exist / Requested');
            $table->string('kp_ready', 100)->nullable();
            $table->date('exp_kp')->nullable();
            // ── Compliance: SIM ──
            $table->string('type_sim', 10)->nullable()->comment('A, B1, B2');
            $table->string('no_sim', 30)->nullable();
            $table->date('expired_sim')->nullable();
            // ── Compliance: SIO ──
            $table->string('sio_k3', 5)->default('NO')->comment('YES / NO');
            $table->string('no_sio', 50)->nullable();
            $table->date('expire_sio')->nullable();
            $table->string('nama_perusahaan_sio', 200)->nullable();
            $table->string('tipe_sio', 100)->nullable();
            // ── CCPM / Project ──
            $table->string('ccpm', 30)->nullable()->comment('AKTIF / NONAKTIF');
            $table->string('hes_passport', 50)->nullable();
            // ── MCU ──
            $table->date('exp_mcu')->nullable();
            $table->string('status_mcu', 20)->nullable()->comment('OK / NOT OK');
            $table->string('lokasi_mcu', 100)->nullable();
            // ── PPE ──
            $table->string('ukuran_baju', 10)->nullable()->comment('S, M, L, XL, XXL, XXXL');
            $table->string('ukuran_sepatu', 10)->nullable();
            // ── Training ──
            $table->date('tanggal_hi')->nullable()->comment('Tanggal HI / Hazard Identification training');
            $table->string('nama_trainer_hi', 150)->nullable();
            $table->date('swp_pt_ha')->nullable();
            $table->string('nama_trainer_swp', 150)->nullable();
            $table->decimal('hasil_posttest_swp', 5, 2)->nullable();
            $table->string('status_posttest_pwtha', 20)->nullable()->comment('PASS / FAIL');
            $table->string('post_test_mvshe', 50)->nullable();
            $table->string('spotter_flagman', 10)->nullable()->comment('YES / NO');
            // ── PKWT ──
            $table->date('start_pkwt')->nullable();
            $table->date('end_pkwt')->nullable();
            $table->integer('bln_pkwt')->nullable();
            $table->string('no_contract', 100)->nullable();
            // ── Disnaker ──
            $table->string('disnaker', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('position_id')->references('id')->on('positions')->onDelete('set null');
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
            $table->index(['status', 'group']);
            $table->index('expired_sim');
            $table->index('expire_badge');
            $table->index('exp_mcu');
        });
    }
    public function down(): void { Schema::dropIfExists('employees'); }
};
