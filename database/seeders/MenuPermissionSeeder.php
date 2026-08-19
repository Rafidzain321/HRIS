<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class MenuPermissionSeeder extends Seeder
{
    // Permission izin menu di-assign langsung ke masing-masing user (lihat UserManagementController),
    // bukan lewat role — seeder ini cuma memastikan baris permission-nya ada di database.
    public function run(): void
    {
        $menus = config('menus');

        $permissions = [];
        foreach ($menus as $key => $menu) {
            $permissions[] = "view-{$key}";
            if ($menu['edit']) {
                $permissions[] = "edit-{$key}";
            }
        }

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
    }
}
