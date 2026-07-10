<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Department;
use App\Models\Position;
use App\Models\Employee;
use Spatie\Permission\Models\Role;
use App\Models\Equipment;
use App\Models\EquipmentOperator;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── ROLES ──
        foreach (['super-admin', 'hr-staff', 'manager', 'viewer'] as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        // ── DEPARTMENTS (berdasarkan data AKM) ──
        $depts = [
            ['nama' => 'Manpower AKM',    'kode' => 'AKM'],
            ['nama' => 'HES / Safety',    'kode' => 'HES'],
            ['nama' => 'Produksi',         'kode' => 'PROD'],
            ['nama' => 'Logistik',         'kode' => 'LOG'],
            ['nama' => 'Maintenance',      'kode' => 'MNT'],
            ['nama' => 'Administrasi',     'kode' => 'ADM'],
            ['nama' => 'CCPM Project',     'kode' => 'CCPM'],
        ];
        foreach ($depts as $d) {
            Department::firstOrCreate(['kode' => $d['kode']], $d);
        }

        $akm  = Department::where('kode', 'AKM')->first();
        $hes  = Department::where('kode', 'HES')->first();
        $log  = Department::where('kode', 'LOG')->first();
        $prod = Department::where('kode', 'PROD')->first();

        // ── POSITIONS (jabatan nyata dari Excel) ──
        $positions = [
            ['nama_jabatan' => 'Project Manager',        'level' => 'manager',    'department_id' => $akm->id],
            ['nama_jabatan' => 'Superintenden',          'level' => 'manager',    'department_id' => $akm->id],
            ['nama_jabatan' => 'HES Man',                'level' => 'staff',      'department_id' => $hes->id],
            ['nama_jabatan' => 'PMCOW',                  'level' => 'operator',   'department_id' => $akm->id],
            ['nama_jabatan' => 'Driver Dump Truck',      'level' => 'operator',   'department_id' => $log->id],
            ['nama_jabatan' => 'SPOTTER',                'level' => 'staff',      'department_id' => $akm->id],
            ['nama_jabatan' => 'Rigger',                 'level' => 'operator',   'department_id' => $akm->id],
            ['nama_jabatan' => 'Helper',                 'level' => 'staff',      'department_id' => $akm->id],
            ['nama_jabatan' => 'Pipe Fitter',            'level' => 'staff',      'department_id' => $prod->id],
            ['nama_jabatan' => 'Swamper',                'level' => 'staff',      'department_id' => $log->id],
            ['nama_jabatan' => 'OPERATOR COMPACTOR',     'level' => 'operator',   'department_id' => $prod->id],
            ['nama_jabatan' => 'Welder',                 'level' => 'staff',      'department_id' => $prod->id],
            ['nama_jabatan' => 'Bus Driver',             'level' => 'operator',   'department_id' => $log->id],
            ['nama_jabatan' => 'Operator Motor Grader',  'level' => 'operator',   'department_id' => $prod->id],
            ['nama_jabatan' => 'Excavator Operator',     'level' => 'operator',   'department_id' => $prod->id],
            ['nama_jabatan' => 'Driver Water Tank',      'level' => 'operator',   'department_id' => $log->id],
            ['nama_jabatan' => 'Flagman',                'level' => 'staff',      'department_id' => $akm->id],
            ['nama_jabatan' => 'Instrument Survey',      'level' => 'staff',      'department_id' => $akm->id],
            ['nama_jabatan' => 'Operator Excavator',     'level' => 'operator',   'department_id' => $prod->id],
            ['nama_jabatan' => 'Material Man',           'level' => 'staff',      'department_id' => $akm->id],
            ['nama_jabatan' => 'Teknisi Cable Locator',  'level' => 'staff',      'department_id' => $prod->id],
            ['nama_jabatan' => 'DRIVER BUS',             'level' => 'operator',   'department_id' => $log->id],
            ['nama_jabatan' => 'Crane Operator',         'level' => 'operator',   'department_id' => $prod->id],
        ];
        foreach ($positions as $pos) {
            Position::firstOrCreate(['nama_jabatan' => $pos['nama_jabatan']], $pos);
        }

        // ── SAMPLE EMPLOYEES (dari Excel AKM real) ──
        $pm = Position::where('nama_jabatan', 'Project Manager')->first();
        $supt = Position::where('nama_jabatan', 'Superintenden')->first();
        $hesman = Position::where('nama_jabatan', 'HES Man')->first();
        $driver = Position::where('nama_jabatan', 'Driver Dump Truck')->first();
        $spotter = Position::where('nama_jabatan', 'SPOTTER')->first();
        $pmcow = Position::where('nama_jabatan', 'PMCOW')->first();

        $sampleEmployees = [
            [
                'id_badge' => 'AKM-EW-0001', 'nama_lengkap' => 'BAWONO WIJAYANTO WIRANJAYA',
                'jabatan' => 'Project Manager', 'position_id' => $pm->id, 'department_id' => $akm->id,
                'no_ktp' => '3471111311710001', 'tempat_lahir' => 'YOGYAKARTA',
                'tanggal_lahir' => '1971-11-13', 'kota_asal' => 'YOGYAKARTA',
                'type_sim' => 'A', 'no_sim' => null, 'expired_sim' => '2025-06-15',
                'sio_k3' => 'NO', 'ccpm' => 'AKTIF',
                'expire_badge' => '2026-09-24', 'status_kp' => 'KP has been exist',
                'exp_kp' => '2026-01-24', 'exp_mcu' => '2026-09-24', 'status_mcu' => 'OK',
                'lokasi_mcu' => 'RS MUTIA SARI', 'ukuran_baju' => 'XXXL', 'ukuran_sepatu' => '8',
                'status_posttest_pwtha' => 'PASS', 'hasil_posttest_swp' => 93,
                'start_pkwt' => '2025-10-01', 'end_pkwt' => '2026-09-30',
            ],
            [
                'id_badge' => 'AKM-EW-0120', 'nama_lengkap' => 'NEDRIYANTO',
                'jabatan' => 'Superintenden', 'position_id' => $supt->id, 'department_id' => $akm->id,
                'no_ktp' => '1471083108690001', 'tempat_lahir' => 'MEDAN',
                'tanggal_lahir' => '1969-08-31', 'kota_asal' => 'MEDAN',
                'type_sim' => null, 'sio_k3' => 'NO', 'ccpm' => 'AKTIF',
                'status_mcu' => 'OK', 'ukuran_baju' => 'M', 'ukuran_sepatu' => '6',
                'status_posttest_pwtha' => 'PASS', 'hasil_posttest_swp' => 93,
                'start_pkwt' => '2025-10-01', 'end_pkwt' => '2026-09-30',
            ],
        ];

        foreach ($sampleEmployees as $emp) {
            $jabatan = $emp['jabatan'];
            unset($emp['jabatan']);
            Employee::firstOrCreate(['id_badge' => $emp['id_badge']], array_merge($emp, ['status' => 'AKTIF', 'group' => 'AKM']));
        }

        // ── ADMIN USERS ──
        $admin = User::firstOrCreate(['email' => 'admin@akm-wur.com'], [
            'name' => 'Super Admin AKM', 'password' => Hash::make('Admin@AKM2026!'),
        ]);
        $admin->assignRole('super-admin');

        $hr = User::firstOrCreate(['email' => 'hr@akm-wur.com'], [
            'name' => 'HR Staff AKM', 'password' => Hash::make('Hr@AKM2026!'),
        ]);
        $hr->assignRole('hr-staff');

        $this->call(EquipmentSeeder::class);

        $this->command->info('');
        $this->command->info(' Seeder selesai!');
        $this->command->table(
            ['Role', 'Email', 'Password'],
            [
                ['super-admin', 'admin@akm-wur.com', 'Admin@AKM2026!'],
                ['hr-staff',    'hr@akm-wur.com',    'Hr@AKM2026!'],
            ]
        );
    }
}
