<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        DB::statement("ALTER TABLE employee_documents MODIFY COLUMN tipe ENUM(
            'foto','ktp','kk','bpjs','bpjs_kesehatan','skck','sio','cv','k3u','lainnya'
        )");
    }
    public function down(): void {
        DB::statement("ALTER TABLE employee_documents MODIFY COLUMN tipe ENUM(
            'foto','ktp','kk','bpjs','bpjs_kesehatan','skck','sio','cv','lainnya'
        )");
    }
};