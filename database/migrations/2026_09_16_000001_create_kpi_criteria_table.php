<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Daftar baku 20 poin penilaian (5 Keselamatan + 15 Produktivitas/Keandalan/Kerjasama/
    // Komunikasi) sesuai form penilaian resmi PT. Andalas Karya Mulia. department_id NULL
    // berarti berlaku untuk semua karyawan; kalau diisi berarti poin tambahan khusus
    // departemen itu saja (bisa ditambah atasan langsung atau HRD lewat halaman Kriteria).
    public function up(): void
    {
        Schema::create('kpi_criteria', function (Blueprint $table) {
            $table->id();
            $table->enum('section', ['A', 'B']); // A = Keselamatan (40%), B = Produktivitas dst (60%)
            $table->string('sub_kategori', 100)->nullable(); // grup tampilan dalam section B
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->string('deskripsi', 500);
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        $now = now();
        $baseCriteria = [
            // Section A — Aspek Keselamatan (40%)
            ['section' => 'A', 'sub_kategori' => null, 'urutan' => 1, 'deskripsi' => 'Mengikuti pertemuan meeting internal team'],
            ['section' => 'A', 'sub_kategori' => null, 'urutan' => 2, 'deskripsi' => 'Pakai PPE/APD / Seragam / Pakaian Kerja saat melakukan pekerjaan'],
            ['section' => 'A', 'sub_kategori' => null, 'urutan' => 3, 'deskripsi' => 'Menjaga inventarisasi yang diberikan'],
            ['section' => 'A', 'sub_kategori' => null, 'urutan' => 4, 'deskripsi' => 'Melakukan house keeping pada ruangan / tempat kerja'],
            ['section' => 'A', 'sub_kategori' => null, 'urutan' => 5, 'deskripsi' => 'Beradaptasi terhadap perubahan & memiliki antusias terhadap ide baru'],
            // Section B — Produktifitas Kerja (1-3)
            ['section' => 'B', 'sub_kategori' => 'Produktifitas Kerja', 'urutan' => 1, 'deskripsi' => 'Bekerja sesuai SOP dan instruksi kerja'],
            ['section' => 'B', 'sub_kategori' => 'Produktifitas Kerja', 'urutan' => 2, 'deskripsi' => 'Keseriusan & Perencanaan dalam bekerja'],
            ['section' => 'B', 'sub_kategori' => 'Produktifitas Kerja', 'urutan' => 3, 'deskripsi' => 'Bekerja sesuai target yang diberikan, Ketepatan waktu & kualitas hasil kerja'],
            // Keandalan / Kemampuan (4-8)
            ['section' => 'B', 'sub_kategori' => 'Keandalan / Kemampuan', 'urutan' => 4, 'deskripsi' => 'Kedisiplinan dan Kehadiran'],
            ['section' => 'B', 'sub_kategori' => 'Keandalan / Kemampuan', 'urutan' => 5, 'deskripsi' => 'Menepati deadline target kerja'],
            ['section' => 'B', 'sub_kategori' => 'Keandalan / Kemampuan', 'urutan' => 6, 'deskripsi' => 'Inisiatif, Kreatif dan inovatif'],
            ['section' => 'B', 'sub_kategori' => 'Keandalan / Kemampuan', 'urutan' => 7, 'deskripsi' => 'Etika / Etitude dalam bekerja'],
            ['section' => 'B', 'sub_kategori' => 'Keandalan / Kemampuan', 'urutan' => 8, 'deskripsi' => 'Pengisian dokumen kerja & Tertib Administrasi'],
            // Kerjasama Team (9-12)
            ['section' => 'B', 'sub_kategori' => 'Kerjasama Team', 'urutan' => 9, 'deskripsi' => 'Sikap positif dalam kerjasama team'],
            ['section' => 'B', 'sub_kategori' => 'Kerjasama Team', 'urutan' => 10, 'deskripsi' => 'Laporan pekerjaan yang dilakukan ke atasan langsung'],
            ['section' => 'B', 'sub_kategori' => 'Kerjasama Team', 'urutan' => 11, 'deskripsi' => 'Keterbukaan atas saran dan masukan'],
            ['section' => 'B', 'sub_kategori' => 'Kerjasama Team', 'urutan' => 12, 'deskripsi' => 'Antusias menerima tugas baru'],
            // Komunikasi (13-15)
            ['section' => 'B', 'sub_kategori' => 'Komunikasi', 'urutan' => 13, 'deskripsi' => 'Komunikasi dengan atasan, team, dan client / pemberi kerja'],
            ['section' => 'B', 'sub_kategori' => 'Komunikasi', 'urutan' => 14, 'deskripsi' => 'Menyampaikan Permasalahan Kerja Pada atasan dan Team'],
            ['section' => 'B', 'sub_kategori' => 'Komunikasi', 'urutan' => 15, 'deskripsi' => 'Jujur dan bertindak benar dalam menyampaikan amanat pekerjaan'],
        ];

        foreach ($baseCriteria as $c) {
            DB::table('kpi_criteria')->insert(array_merge($c, [
                'department_id' => null,
                'created_by'    => null,
                'is_active'     => true,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_criteria');
    }
};
