<?php
// database/seeders/ImportPurnamaSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Project;
use App\Models\EmployeeTraining;
use App\Models\TrainingType;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ImportPurnamaSeeder extends Seeder
{
    public function run(): void
    {
        $project = Project::where('kode', 'purnama')->firstOrFail();

        $data = json_decode(file_get_contents(database_path('data/purnama_employees.json')), true);

        $inserted = 0;
        $skipped  = 0;
        $trainingInserted = 0;

        foreach ($data as $row) {
            if (empty($row['id_badge'])) { $skipped++; continue; }

            // Cari atau buat jabatan
            $position = null;
            if (!empty($row['jabatan'])) {
                $position = Position::firstOrCreate(
                    ['nama_jabatan' => $row['jabatan']],
                    ['nama_jabatan' => $row['jabatan']]
                );
            }

            // Skip kalau sudah ada
            if (Employee::where('id_badge', $row['id_badge'])->exists()) {
                $skipped++;
                continue;
            }

            $employee = Employee::create([
                'project_id'          => $project->id,
                'position_id'         => $position?->id,
                'id_badge'            => $row['id_badge'],
                'nama_lengkap'        => $row['nama_lengkap'],
                'status'              => $row['status'] ?? 'AKTIF',
                'sio_k3'              => $row['sio_k3'] ?? 'NO',
                'no_telepon'          => $row['no_telepon'],
                'tempat_lahir'        => $row['tempat_lahir'],
                'tanggal_lahir'       => $row['tanggal_lahir'] ? Carbon::parse($row['tanggal_lahir']) : null,
                'alamat'              => $row['alamat'],
                'kota_asal'           => $row['kota_asal'],
                'no_ktp'              => $row['no_ktp'],
                'ccpm'                => $row['ccpm'],
                'expire_badge'        => $row['expire_badge'] ? Carbon::parse($row['expire_badge']) : null,
                'status_kp'           => $row['status_kp'],
                'kp_ready'            => $row['kp_ready'],
                'exp_kp'              => $row['exp_kp'] ? Carbon::parse($row['exp_kp']) : null,
                'type_sim'            => $row['type_sim'],
                'no_sim'              => $row['no_sim'],
                'expired_sim'         => $row['expired_sim'] ? Carbon::parse($row['expired_sim']) : null,
                'rfid'                => $row['rfid'],
                'no_sio'              => $row['no_sio'],
                'expire_sio'          => $row['expire_sio'] ? Carbon::parse($row['expire_sio']) : null,
                'nama_perusahaan_sio' => $row['nama_perusahaan_sio'],
                'tipe_sio'            => $row['tipe_sio'],
                'tamatan'             => $row['tamatan'],
                'tanggal_hi'          => $row['tanggal_hi'] ? Carbon::parse($row['tanggal_hi']) : null,
                'exp_mcu'             => $row['exp_mcu'] ? Carbon::parse($row['exp_mcu']) : null,
                'status_mcu'          => $row['status_mcu'],
                'lokasi_mcu'          => $row['lokasi_mcu'],
                'ukuran_baju'         => $row['ukuran_baju'],
                'ukuran_sepatu'       => $row['ukuran_sepatu'],
            ]);

            $inserted++;

            // Import training
            if (!empty($row['trainings'])) {
                foreach ($row['trainings'] as $namaTraining => $tData) {
                    if (empty($tData['tanggal'])) continue;

                    $tt = TrainingType::where('nama', $namaTraining)->first();
                    if (!$tt) {
                        Log::warning("TrainingType tidak ditemukan: {$namaTraining}");
                        continue;
                    }

                    // Skip kalau sudah ada
                    if (EmployeeTraining::where('employee_id', $employee->id)->where('training_type_id', $tt->id)->exists()) {
                        continue;
                    }

                    EmployeeTraining::create([
                        'employee_id'      => $employee->id,
                        'training_type_id' => $tt->id,
                        'tanggal'          => Carbon::parse($tData['tanggal']),
                        'nama_trainer'     => $tData['nama_trainer'] ?? null,
                        'nilai'            => $tData['nilai'] ?? null,
                        'status'           => $tData['status'] ?? null,
                        'input_by'         => 1,
                    ]);
                    $trainingInserted++;
                }
            }
        }

        $this->command->info("✅ Import Purnama selesai:");
        $this->command->info("   Karyawan inserted : {$inserted}");
        $this->command->info("   Karyawan skipped  : {$skipped}");
        $this->command->info("   Training inserted  : {$trainingInserted}");
    }
}