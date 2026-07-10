<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ppe', function (Blueprint $table) {
            $table->date('tgl_helm_orange')->nullable()->after('tgl_helm_putih');
        });
    }

    public function down(): void
    {
        Schema::table('ppe', function (Blueprint $table) {
            $table->dropColumn('tgl_helm_orange');
        });
    }
};
