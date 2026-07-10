<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Project;
use App\Models\Employee;

class ProjectAndUserSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Buat Projects ──────────────────────────────────
        $projects = [
            [
                'kode'             => 'giam',
                'nama'             => 'Central WUR Giam',
                'lokasi'           => 'Giam, Duri',
                'tipe_timesheet'   => '7jam',
                'tipe_gaji'        => 'giam',
                'warna'            => '#3A8FE0',
                'is_active'        => true,
            ],
            [
                'kode'             => 'md',
                'nama'             => 'Multi Disiplin (MD)',
                'lokasi'           => 'Duri',
                'tipe_timesheet'   => '8jam',
                'tipe_gaji'        => 'md',
                'warna'            => '#22C97A',
                'is_active'        => true,
            ],
            [
                'kode'             => 'khawista',
                'nama'             => 'Khawista',
                'lokasi'           => 'Duri',
                'tipe_timesheet'   => '7jam',
                'tipe_gaji'        => 'giam',
                'warna'            => '#E8A020',
                'is_active'        => true,
            ],
            [
                'kode'             => 'purnama',
                'nama'             => 'Purnama',
                'lokasi'           => 'Duri',
                'tipe_timesheet'   => '7jam',
                'tipe_gaji'        => 'giam',
                'warna'            => '#9B59B6',
                'is_active'        => true,
            ],
        ];

        $projectIds = [];
        foreach ($projects as $p) {
            $proj = Project::updateOrCreate(['kode' => $p['kode']], $p);
            $projectIds[$p['kode']] = $proj->id;
        }

        // ── 2. Update semua employee existing → project giam ─
        Employee::whereNull('project_id')->update([
            'project_id' => $projectIds['giam'],
        ]);

        // ── 3. Buat / Update Users ────────────────────────────
        $users = [
            // Super Admin — akses semua project
            [
                'name'       => 'Super Admin AKM',
                'email'      => 'superadmin@akm.com',
                'password'   => Hash::make('AKM@superadmin2024'),
                'role'       => 'super-admin',
                'project_id' => null,
                'is_active'  => true,
            ],
            // Viewer — lihat semua, tidak bisa edit
            [
                'name'       => 'Viewer AKM',
                'email'      => 'viewer@akm.com',
                'password'   => Hash::make('AKM@viewer2024'),
                'role'       => 'viewer',
                'project_id' => null,
                'is_active'  => true,
            ],
            // Giam — Irvan
            [
                'name'       => 'Irvan',
                'email'      => 'irvan@akm-giam.com',
                'password'   => Hash::make('Giam@irvan2024'),
                'role'       => 'project-user',
                'project_id' => $projectIds['giam'],
                'is_active'  => true,
            ],
            // Giam — Alif
            [
                'name'       => 'Alif',
                'email'      => 'alif@akm-giam.com',
                'password'   => Hash::make('Giam@alif2024'),
                'role'       => 'project-user',
                'project_id' => $projectIds['giam'],
                'is_active'  => true,
            ],
            // Khawista — Dedi
            [
                'name'       => 'Dedi',
                'email'      => 'dedi@akm-khawista.com',
                'password'   => Hash::make('Khawista@dedi2024'),
                'role'       => 'project-user',
                'project_id' => $projectIds['khawista'],
                'is_active'  => true,
            ],
            // MD — Bahri
            [
                'name'       => 'Bahri',
                'email'      => 'bahri@akm-md.com',
                'password'   => Hash::make('MD@bahri2024'),
                'role'       => 'project-user',
                'project_id' => $projectIds['md'],
                'is_active'  => true,
            ],
            // Purnama — kosong dulu, bisa diupdate nanti
            [
                'name'       => 'User Purnama',
                'email'      => 'user@akm-purnama.com',
                'password'   => Hash::make('Purnama@user2024'),
                'role'       => 'project-user',
                'project_id' => $projectIds['purnama'],
                'is_active'  => false, // nonaktif dulu sampai ada orangnya
            ],
        ];

        foreach ($users as $u) {
            $role = $u['role'];
            unset($u['role']);
            $user = User::updateOrCreate(['email' => $u['email']], $u);
            $user->syncRoles([$role]);
        }

        // ── 4. Buat role di Spatie kalau belum ada ────────────
        $roles = ['super-admin', 'project-user', 'viewer'];
        foreach ($roles as $r) {
            \Spatie\Permission\Models\Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }
    }
}