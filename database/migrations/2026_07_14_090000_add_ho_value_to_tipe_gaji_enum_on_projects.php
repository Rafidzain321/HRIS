<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TABLE projects MODIFY tipe_gaji ENUM('giam','md','ho') DEFAULT 'giam'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE projects MODIFY tipe_gaji ENUM('giam','md') DEFAULT 'giam'");
    }
};
