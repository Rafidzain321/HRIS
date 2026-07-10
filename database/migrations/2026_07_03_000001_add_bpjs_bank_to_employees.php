<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('no_bpjs_tk', 20)->nullable()->after('no_rekening');
            $table->string('no_bpjs_kes', 20)->nullable()->after('no_bpjs_tk');
            $table->string('nama_bank', 50)->nullable()->after('no_bpjs_kes');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['no_bpjs_tk', 'no_bpjs_kes', 'nama_bank']);
        });
    }
};