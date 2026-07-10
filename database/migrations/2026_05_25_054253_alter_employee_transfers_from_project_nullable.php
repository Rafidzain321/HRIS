<?php
// 2026_05_25_054253_alter_employee_transfers_from_project_nullable
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('employee_transfers', function (Blueprint $table) {
            $table->foreignId('from_project_id')->nullable()->change();
        });
    }
};
