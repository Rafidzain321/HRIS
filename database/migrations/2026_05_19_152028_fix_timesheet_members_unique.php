<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('timesheet_members', function (Blueprint $table) {
            $table->dropUnique(['id_badge']);
            $table->unique(['id_badge', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::table('timesheet_members', function (Blueprint $table) {
            $table->dropUnique(['id_badge', 'project_id']);
            $table->unique(['id_badge']);
        });
    }
};
