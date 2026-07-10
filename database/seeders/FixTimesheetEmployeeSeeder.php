<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Department;

class FixTimesheetEmployeeSeeder extends Seeder
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

        // ─────────────────────────────────────────────────────────
        // 1. RENAME nama yang salah eja di DB → sesuai nama AKM
        //    Format: [nama_di_db, nama_yang_benar]
        // ─────────────────────────────────────────────────────────
        $renames = [
            // nama di DB yang perlu difix (ikut nama AKM sebagai referensi)
            // tidak rename YUDA karena di AKM memang YUDA YULIANDA PRATAMA
            // di timesheet ditulis YUDHA PRATAMA → ini nickname saja
        ];

        foreach ($renames as [$lama, $baru]) {
            $emp = Employee::whereRaw('UPPER(nama_lengkap) = ?', [strtoupper($lama)])->first();
            if ($emp) {
                $emp->update(['nama_lengkap' => $baru]);
                $this->command->info("✏️  Rename: {$lama} → {$baru}");
            }
        }

        // ─────────────────────────────────────────────────────────
        // 2. UPDATE JABATAN untuk 56 karyawan timesheet
        //    Format: [badge_akm, jabatan_benar]
        //    Pakai badge sebagai key karena lebih akurat dari nama
        // ─────────────────────────────────────────────────────────
        $updates = [
            // EXACT MATCH — update jabatan sesuai timesheet
            ['AKM-EW-0096', 'PMCOW'],
            ['AKM-EW-0065', 'HES Man'],
            ['AKM-EW-0099', 'PMCOW'],
            ['AKM-EW-0097', 'PMCOW Survey'],
            ['AKM-EW-0066', 'HES Man'],
            ['AKM-EW-0072', 'HES Man'],
            ['AKM-EW-0067', 'HES Man'],
            ['AKM-EW-0069', 'HES Man'],
            ['AKM-EW-0074', 'Instrument Survey'],
            ['AKM-EW-0100', 'PMCOW'],
            ['AKM-EW-0101', 'PMCOW Survey'],
            ['AKM-EW-0064', 'HES Man'],
            ['AKM-EW-0190', 'PMCOW'],
            ['AKM-EW-0174', 'HES Man'],
            ['AKM-EW-0061', 'Operator Cable Locator'],
            ['AKM-EW-0059', 'Spotter'],
            ['AKM-EW-0057', 'Spotter'],
            ['AKM-EW-0110', 'Spotter'],
            ['AKM-EW-0119', 'Spotter'],
            ['AKM-EW-0051', 'Helper'],
            ['AKM-EW-0052', 'Spotter'],
            ['AKM-EW-0111', 'Helper'],
            ['AKM-EW-0053', 'Flagman'],
            ['AKM-EW-0054', 'Helper'],
            ['AKM-EW-0258', 'Spotter'],
            ['AKM-EW-0060', 'Helper Survey'],
            ['AKM-EW-0129', 'Spotter'],
            ['AKM-EW-0194', 'Spotter'],
            ['AKM-EW-0112', 'Spotter'],
            ['AKM-EW-0114', 'Spotter'],
            ['AKM-EW-0198', 'Spotter'],
            ['AKM-EW-0200', 'Spotter'],
            ['AKM-EW-0169', 'Helper'],
            ['AKM-EW-0109', 'Spotter'],
            ['AKM-EW-0170', 'Spotter'],
            ['AKM-EW-0232', 'Helper'],
            ['AKM-EW-0266', 'Helper'],      // WAHYU RIO PRIMA
            ['AKM-EW-0126', 'Swamper Fuel Tank'],
            ['AKM-EW-0712', 'Swamper'],     // DEDE MARTIN
            ['AKM-EW-0203', 'Swamper Water Truck'],
            ['AKM-EW-0202', 'Swamper'],
            ['AKM-EW-0657', 'Swamper'],     // HUSEIN AL HAFIZ
            ['AKM-EW-0713', 'Swamper'],     // YUSWARDI

            // BEDA NAMA — sama orang, update via badge
            ['AKM-EW-0197', 'PMCOW'],           // MUHAMMAD YASIR SIREGAR (timesheet: M YASIR SIREGAR)
            ['AKM-EW-0073', 'Instrument Survey'], // EVANDRI WANDA SAPUTRA (timesheet: EVANDRI WANDA)
            ['AKM-EW-0013', 'PMCOW'],            // MUHAMMAD RAPIQI (timesheet: M RAPIQI)
            ['AKM-EW-0103', 'PMCOW Earth Work'], // RIDHO ILLAHI (timesheet: RIDHO ILAHI)
            ['AKM-EW-0068', 'HES Man'],          // YUDA YULIANDA PRATAMA (timesheet: YUDHA PRATAMA)
            ['AKM-EW-0102', 'PMCOW'],            // JAMI MARZUKI (timesheet: JAMI MARUZUKI)
            ['AKM-EW-0117', 'Spotter'],          // MUHAMMAD REVAL AL-AKHYAR (timesheet: M REVAL AL AKHYAR)
            ['AKM-EW-0062', 'Helper Survey'],    // WIHANDRI SENJAYA (timesheet: WIHANDRI SANJAYA)
            ['AKM-EW-0113', 'Spotter'],          // FAJRI KURNIAWAN (timesheet: FAJRI KURNIWAN)
            ['AKM-EW-0236', 'Helper'],           // SASTRO FERNANDO (timesheet: SASTRO)
            ['AKM-EW-0127', 'Swamper Low Boy'],  // MUHAMMAD RENO FAREZI (timesheet: MUHAMMAD RENO)
            ['AKM-EW-0167', 'Swamper FT'],       // M HUSNI IKBAL (timesheet: M HUSNI IQBAL)
        ];

        $updated = 0;
        foreach ($updates as [$badge, $jabatan]) {
            $emp = Employee::where('id_badge', $badge)->first();
            if ($emp) {
                $emp->update(['position_id' => $getPos($jabatan)]);
                $updated++;
            } else {
                $this->command->warn("⚠️  Badge tidak ditemukan: {$badge}");
            }
        }
        $this->command->info("✅ Updated jabatan: {$updated} karyawan");

        // ─────────────────────────────────────────────────────────
        // 3. TAMBAH karyawan yang tidak ada di DB
        //    BOBBI NAZMI SETIAWAN tidak ada di AKM list → tambah
        // ─────────────────────────────────────────────────────────
        $toAdd = [
            // [id_badge, nama_lengkap, jabatan]
            // BOBBI NAZMI SETIAWAN tidak ada di AKM list sama sekali
            // Kemungkinan karyawan baru atau badge belum terdaftar
            // Skip dulu sampai ada badge resminya
        ];

        // Cek apakah BOBBI NAZMI SETIAWAN sudah ada
        $bobbi = Employee::whereRaw('UPPER(nama_lengkap) LIKE ?', ['%BOBBI%'])->first();
        if (!$bobbi) {
            $this->command->warn("⚠️  BOBBI NAZMI SETIAWAN tidak ada di DB dan tidak ada di AKM list");
            $this->command->warn("    → Perlu badge resmi sebelum bisa ditambahkan");
        } else {
            $this->command->info("✅ BOBBI NAZMI SETIAWAN sudah ada: [{$bobbi->id_badge}] {$bobbi->nama_lengkap}");
        }

        // ─────────────────────────────────────────────────────────
        // 4. Pastikan SYAHRUL yang benar (ada 2: -0074 dan -0077.)
        //    Yang di timesheet = AKM-EW-0074 (tanpa titik)
        // ─────────────────────────────────────────────────────────
        $syahrul074 = Employee::where('id_badge', 'AKM-EW-0074')->first();
        $syahrul077 = Employee::where('id_badge', 'AKM-EW-0077')->first();
        if ($syahrul074) {
            $syahrul074->update(['position_id' => $getPos('Instrument Survey')]);
            $this->command->info("✅ SYAHRUL [AKM-EW-0074] → Instrument Survey");
        }
        if ($syahrul077) {
            $this->command->info("ℹ️  SYAHRUL. [AKM-EW-0077] → jabatan tidak diubah (bukan yang di timesheet)");
        }

        $this->command->info("\n✅ FixTimesheetEmployeeSeeder selesai!");
    }
}
