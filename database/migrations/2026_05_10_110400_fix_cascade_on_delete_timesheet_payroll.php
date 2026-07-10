<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Fix timesheets — hapus CASCADE, ganti RESTRICT
        DB::statement('ALTER TABLE timesheets DROP FOREIGN KEY timesheets_employee_id_foreign');
        DB::statement('ALTER TABLE timesheets ADD CONSTRAINT timesheets_employee_id_foreign 
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE RESTRICT');

        // Fix employee_payroll — hapus CASCADE, ganti RESTRICT
        DB::statement('ALTER TABLE employee_payroll DROP FOREIGN KEY employee_payroll_employee_id_foreign');
        DB::statement('ALTER TABLE employee_payroll ADD CONSTRAINT employee_payroll_employee_id_foreign 
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE RESTRICT');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE timesheets DROP FOREIGN KEY timesheets_employee_id_foreign');
        DB::statement('ALTER TABLE timesheets ADD CONSTRAINT timesheets_employee_id_foreign 
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE');

        DB::statement('ALTER TABLE employee_payroll DROP FOREIGN KEY employee_payroll_employee_id_foreign');
        DB::statement('ALTER TABLE employee_payroll ADD CONSTRAINT employee_payroll_employee_id_foreign 
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE');
    }
};