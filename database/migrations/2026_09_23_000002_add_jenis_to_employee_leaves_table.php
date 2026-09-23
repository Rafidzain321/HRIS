<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // "Izin Tanpa Pemotongan Cuti" — jenis pengajuan baru yang dicatat di tabel yang sama
    // supaya riwayatnya tetap tercatat & bisa di-tracking, tapi TIDAK ikut mengurangi
    // jatah/sisa Cuti Tahunan (lihat filter jenis di EmployeeLeave::hariCutiDalamBulan()
    // dan perhitungan "terpakai" di EmployeeLeaveController & Cuti/Index.jsx).
    public function up(): void
    {
        Schema::table('employee_leaves', function (Blueprint $table) {
            $table->string('jenis', 30)->default('cuti_tahunan')->after('employee_id');
        });
    }

    public function down(): void
    {
        Schema::table('employee_leaves', function (Blueprint $table) {
            $table->dropColumn('jenis');
        });
    }
};
