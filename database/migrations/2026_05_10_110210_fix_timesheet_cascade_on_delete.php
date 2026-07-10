<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Hapus foreign key lama yang CASCADE
        DB::statement('ALTER TABLE timesheets DROP FOREIGN KEY timesheets_employee_id_foreign');
        
        // Buat ulang tanpa CASCADE — pakai RESTRICT supaya tidak bisa hapus karyawan kalau masih ada timesheet
        DB::statement('ALTER TABLE timesheets ADD CONSTRAINT timesheets_employee_id_foreign 
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE RESTRICT');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE timesheets DROP FOREIGN KEY timesheets_employee_id_foreign');
        DB::statement('ALTER TABLE timesheets ADD CONSTRAINT timesheets_employee_id_foreign 
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE');
    }
};