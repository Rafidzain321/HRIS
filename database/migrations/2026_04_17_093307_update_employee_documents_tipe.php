<?php
// database/migrations/xxxx_update_employee_documents_tipe.php
// Tambah tipe: foto, kk, bpjs
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // MySQL: ubah kolom tipe supaya support nilai baru
        \DB::statement("ALTER TABLE employee_documents MODIFY tipe ENUM('ktp','sio','foto','kk','bpjs','skck','lainnya') NOT NULL");
    }
    public function down(): void
    {
        \DB::statement("ALTER TABLE employee_documents MODIFY tipe ENUM('ktp','sio','lainnya') NOT NULL");
    }
};
