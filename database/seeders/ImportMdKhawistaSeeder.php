<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Project;
use Carbon\Carbon;

class ImportMdKhawistaSeeder extends Seeder
{
    public function run(): void
    {
        // Ambil project IDs
        $projectMd       = Project::where('kode', 'md')->first();
        $projectKhawista = Project::where('kode', 'khawista')->first();

        if (!$projectMd) {
            $this->command->error('Project MD tidak ditemukan! Jalankan ProjectAndUserSeeder dulu.');
            return;
        }
        if (!$projectKhawista) {
            $this->command->error('Project Khawista tidak ditemukan! Jalankan ProjectAndUserSeeder dulu.');
            return;
        }

        // Cache positions
        $positions = Position::pluck('id', 'nama_jabatan')->toArray();

        $stats = ['md_imported' => 0, 'md_skipped' => 0, 'kh_imported' => 0, 'kh_skipped' => 0, 'errors' => []];

        // ── Import MD ─────────────────────────────────────────
        $mdJson = file_get_contents(database_path('data/md_employees.json'));
        $mdData = json_decode($mdJson, true);

        $this->command->info("Importing MD: {$projectMd->nama} ({$projectMd->id})...");

        foreach ($mdData as $row) {
            try {
                // Skip kalau badge atau nama kosong
                if (empty($row['id_badge']) || empty($row['nama_lengkap'])) continue;

                // Skip kalau badge sudah ada
                if (Employee::where('id_badge', $row['id_badge'])->exists()) {
                    $stats['md_skipped']++;
                    continue;
                }

                // Skip kalau NIK sudah ada
                if (!empty($row['no_ktp']) && Employee::where('no_ktp', $row['no_ktp'])->exists()) {
                    $stats['md_skipped']++;
                    continue;
                }

                // Resolve jabatan
                $positionId = null;
                if (!empty($row['jabatan'])) {
                    if (!isset($positions[$row['jabatan']])) {
                        $pos = Position::create(['nama_jabatan' => $row['jabatan']]);
                        $positions[$row['jabatan']] = $pos->id;
                    }
                    $positionId = $positions[$row['jabatan']];
                }

                Employee::create([
                    'project_id'          => $projectMd->id,
                    'id_badge'            => $row['id_badge'],
                    'nama_lengkap'        => $row['nama_lengkap'],
                    'no_ktp'              => $row['no_ktp'] ?: null,
                    'no_telepon'          => $row['no_telepon'] ?: null,
                    'tempat_lahir'        => $row['tempat_lahir'] ?: null,
                    'tanggal_lahir'       => $row['tanggal_lahir'] ?: null,
                    'tanggal_masuk'       => $row['tanggal_masuk'] ?: null,
                    'alamat'              => $row['alamat'] ?: null,
                    'kota_asal'           => $row['kota_asal'] ?: null,
                    'group'               => $row['group'] ?: 'AKM-MD',
                    'sio_k3'              => $row['sio_k3'] ?: 'NO',
                    'status'              => $row['status'] ?: 'AKTIF',
                    'ccpm'                => $row['ccpm'] ?: null,
                    'expire_badge'        => $row['expire_badge'] ?: null,
                    'status_kp'           => $row['status_kp'] ?: null,
                    'kp_ready'            => $row['kp_ready'] ?: null,
                    'exp_kp'              => $row['exp_kp'] ?: null,
                    'type_sim'            => $row['type_sim'] ?: null,
                    'no_sim'              => $row['no_sim'] ?: null,
                    'expired_sim'         => $row['expired_sim'] ?: null,
                    'rfid'                => $row['rfid'] ?: null,
                    'no_sio'              => $row['no_sio'] ?: null,
                    'expire_sio'          => $row['expire_sio'] ?: null,
                    'nama_perusahaan_sio' => $row['nama_perusahaan_sio'] ?: null,
                    'tipe_sio'            => $row['tipe_sio'] ?: null,
                    'tamatan'             => $row['tamatan'] ?: null,
                    'exp_mcu'             => $row['exp_mcu'] ?: null,
                    'status_mcu'          => $row['status_mcu'] ?: null,
                    'lokasi_mcu'          => $row['lokasi_mcu'] ?: null,
                    'ukuran_baju'         => $row['ukuran_baju'] ?: null,
                    'ukuran_sepatu'       => $row['ukuran_sepatu'] ?: null,
                    'position_id'         => $positionId,
                ]);

                $stats['md_imported']++;
            } catch (\Exception $e) {
                $stats['errors'][] = "MD [{$row['id_badge']}] {$row['nama_lengkap']}: " . $e->getMessage();
            }
        }

        // ── Import Khawista ───────────────────────────────────
        $khJson = file_get_contents(database_path('data/khawista_employees.json'));
        $khData = json_decode($khJson, true);

        $this->command->info("Importing Khawista: {$projectKhawista->nama} ({$projectKhawista->id})...");

        foreach ($khData as $row) {
            try {
                if (empty($row['id_badge']) || empty($row['nama_lengkap'])) continue;

                // Skip kalau badge sudah ada
                if (Employee::where('id_badge', $row['id_badge'])->exists()) {
                    $stats['kh_skipped']++;
                    continue;
                }

                // Skip kalau NIK sudah ada
                if (!empty($row['no_ktp']) && Employee::where('no_ktp', $row['no_ktp'])->exists()) {
                    $stats['kh_skipped']++;
                    continue;
                }

                // Resolve jabatan
                $positionId = null;
                if (!empty($row['jabatan'])) {
                    if (!isset($positions[$row['jabatan']])) {
                        $pos = Position::create(['nama_jabatan' => $row['jabatan']]);
                        $positions[$row['jabatan']] = $pos->id;
                    }
                    $positionId = $positions[$row['jabatan']];
                }

                Employee::create([
                    'project_id'          => $projectKhawista->id,
                    'id_badge'            => $row['id_badge'],
                    'nama_lengkap'        => $row['nama_lengkap'],
                    'no_ktp'              => $row['no_ktp'] ?: null,
                    'no_telepon'          => $row['no_telepon'] ?: null,
                    'tempat_lahir'        => $row['tempat_lahir'] ?: null,
                    'tanggal_lahir'       => $row['tanggal_lahir'] ?: null,
                    'alamat'              => $row['alamat'] ?: null,
                    'kota_asal'           => $row['kota_asal'] ?: null,
                    'group'               => $row['group'] ?: 'AKM',
                    'sio_k3'              => $row['sio_k3'] ?: 'NO',
                    'status'              => $row['status'] ?: 'AKTIF',
                    'ccpm'                => $row['ccpm'] ?: null,
                    'expire_badge'        => $row['expire_badge'] ?: null,
                    'status_kp'           => $row['status_kp'] ?: null,
                    'kp_ready'            => $row['kp_ready'] ?: null,
                    'exp_kp'              => $row['exp_kp'] ?: null,
                    'type_sim'            => $row['type_sim'] ?: null,
                    'no_sim'              => $row['no_sim'] ?: null,
                    'expired_sim'         => $row['expired_sim'] ?: null,
                    'rfid'                => $row['rfid'] ?: null,
                    'no_sio'              => $row['no_sio'] ?: null,
                    'expire_sio'          => $row['expire_sio'] ?: null,
                    'nama_perusahaan_sio' => $row['nama_perusahaan_sio'] ?: null,
                    'tipe_sio'            => $row['tipe_sio'] ?: null,
                    'tamatan'             => $row['tamatan'] ?: null,
                    'exp_mcu'             => $row['exp_mcu'] ?: null,
                    'status_mcu'          => $row['status_mcu'] ?: null,
                    'lokasi_mcu'          => $row['lokasi_mcu'] ?: null,
                    'ukuran_baju'         => $row['ukuran_baju'] ?: null,
                    'ukuran_sepatu'       => $row['ukuran_sepatu'] ?: null,
                    'position_id'         => $positionId,
                ]);

                $stats['kh_imported']++;
            } catch (\Exception $e) {
                $stats['errors'][] = "KH [{$row['id_badge']}] {$row['nama_lengkap']}: " . $e->getMessage();
            }
        }

        // ── Laporan ───────────────────────────────────────────
        $this->command->info("=== HASIL IMPORT ===");
        $this->command->info("MD     : {$stats['md_imported']} imported, {$stats['md_skipped']} skipped");
        $this->command->info("Khawista: {$stats['kh_imported']} imported, {$stats['kh_skipped']} skipped");
        if (!empty($stats['errors'])) {
            $this->command->warn("Errors (" . count($stats['errors']) . "):");
            foreach (array_slice($stats['errors'], 0, 10) as $err) {
                $this->command->warn("  - $err");
            }
        }
    }
}