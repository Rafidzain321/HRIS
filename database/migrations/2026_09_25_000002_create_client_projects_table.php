<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // "Project" yang sebenarnya (kode kontrak/pekerjaan, mis. "AKM-PP", "AKM-NK-0120") — beda
    // dari tabel `projects` yang sekarang isinya kantor/entitas payroll (Giam, Khawista, MD, dst).
    // Satu karyawan bisa pegang banyak project sekaligus, makanya relasinya many-to-many lewat
    // tabel pivot employee_client_project, bukan foreign key tunggal di employees.
    public function up(): void
    {
        Schema::create('client_projects', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('employee_client_project', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('client_project_id');
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('client_project_id')->references('id')->on('client_projects')->onDelete('cascade');
            $table->unique(['employee_id', 'client_project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_client_project');
        Schema::dropIfExists('client_projects');
    }
};
