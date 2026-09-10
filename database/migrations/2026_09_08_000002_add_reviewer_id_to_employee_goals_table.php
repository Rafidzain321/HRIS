<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('employee_goals', function (Blueprint $table) {
            $table->foreignId('reviewer_id')->nullable()->after('employee_id')
                ->constrained('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employee_goals', function (Blueprint $table) {
            $table->dropForeign(['reviewer_id']);
            $table->dropColumn('reviewer_id');
        });
    }
};
