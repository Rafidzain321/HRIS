<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE employee_documents MODIFY COLUMN tipe ENUM('ktp','sio','foto','kk','bpjs','bpjs_kesehatan','cv','skck','lainnya') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE employee_documents MODIFY COLUMN tipe ENUM('ktp','sio','foto','kk','bpjs','skck','lainnya') NOT NULL");
    }
};
