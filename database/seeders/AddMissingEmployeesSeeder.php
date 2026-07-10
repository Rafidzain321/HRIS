<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Department;

class AddMissingEmployeesSeeder extends Seeder
{
    public function run(): void
    {
        $defaultDept = Department::firstOrCreate(['nama' => 'General'], ['kode' => 'GEN']);
        $posCache = [];

        $getPos = function($jabatan) use (&$posCache, $defaultDept) {
            $key = strtoupper($jabatan);
            if (!isset($posCache[$key])) {
                $pos = Position::whereRaw('UPPER(nama_jabatan) = ?', [$key])->first();
                if (!$pos) {
                    $pos = Position::create(['nama_jabatan' => $jabatan, 'department_id' => $defaultDept->id]);
                }
                $posCache[$key] = $pos->id;
            }
            return $posCache[$key];
        };

        // Karyawan yang ada di AKM list tapi belum ada di DB employees
        // [id_badge, nama_lengkap, jabatan, status]
        $missing = [
            ['AKM-EW-0258', 'MUHAMMAD SYAIFULLAH', 'Spotter',          'AKTIF'],
            ['AKM-EW-0266', 'WAHYU RIO PRIMA',      'Helper',           'AKTIF'],
            ['AKM-EW-0712', 'DEDE MARTIN',           'Swamper',          'AKTIF'],
            ['AKM-EW-0657', 'HUSEIN AL HAFIZ',       'Swamper',          'AKTIF'],
            ['AKM-EW-0713', 'YUSWARDI',              'Swamper',          'AKTIF'],
            ['AKM-EW-0236', 'SASTRO FERNANDO',       'Helper',           'AKTIF'],
        ];

        $added   = 0;
        $skipped = 0;

        foreach ($missing as [$badge, $nama, $jabatan, $status]) {
            // Cek by badge
            $byBadge = Employee::where('id_badge', $badge)->first();
            if ($byBadge) {
                $this->command->line("⏭️  Skip [{$badge}] {$nama} — badge sudah ada ({$byBadge->nama_lengkap})");
                // Update jabatan saja
                $byBadge->update(['position_id' => $getPos($jabatan)]);
                $skipped++;
                continue;
            }

            // Cek by nama
            $byNama = Employee::whereRaw('UPPER(nama_lengkap) = ?', [strtoupper($nama)])->first();
            if ($byNama) {
                $this->command->line("⏭️  Skip {$nama} — nama sudah ada [{$byNama->id_badge}]");
                // Update badge & jabatan
                $byNama->update([
                    'id_badge'    => $badge,
                    'position_id' => $getPos($jabatan),
                ]);
                $skipped++;
                continue;
            }

            // Tambah baru
            Employee::create([
                'id_badge'    => $badge,
                'nama_lengkap'=> $nama,
                'status'      => $status,
                'position_id' => $getPos($jabatan),
            ]);
            $this->command->info("✅ Tambah: [{$badge}] {$nama} → {$jabatan}");
            $added++;
        }

        // Sekarang jalankan update jabatan untuk yang tadi gagal
        $fixes = [
            ['AKM-EW-0258', 'Spotter'],
            ['AKM-EW-0266', 'Helper'],
            ['AKM-EW-0712', 'Swamper'],
            ['AKM-EW-0657', 'Swamper'],
            ['AKM-EW-0713', 'Swamper'],
            ['AKM-EW-0236', 'Helper'],
        ];

        foreach ($fixes as [$badge, $jabatan]) {
            $emp = Employee::where('id_badge', $badge)->first();
            if ($emp) {
                $emp->update(['position_id' => $getPos($jabatan)]);
                $this->command->info("✅ Jabatan fix: [{$badge}] {$emp->nama_lengkap} → {$jabatan}");
            }
        }

        $this->command->info("\n✅ Selesai! Ditambah: {$added} | Diupdate: {$skipped}");
    }
}
