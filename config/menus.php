<?php

// Daftar menu aplikasi & apakah menu itu punya aksi edit (create/update/delete)
// selain lihat. Dipakai sebagai satu-satunya sumber untuk: seeding permission
// Spatie, matriks di admin UI (Pengaturan > Roles & Izin), dan middleware
// `menu` di routes/web.php. Tambah/hapus entri di sini kalau ada menu baru.
return [
    'dashboard'     => ['label' => 'Dashboard',              'edit' => false],
    'karyawan'      => ['label' => 'Data Karyawan',          'edit' => true],
    'sim'           => ['label' => 'SIM Karyawan',           'edit' => true],
    'mcu'           => ['label' => 'MCU',                    'edit' => true],
    'badge'         => ['label' => 'Badge / KP',             'edit' => true],
    'ppe'           => ['label' => 'PPE & Atribut',          'edit' => true],
    'ccpm'          => ['label' => 'Data CCPM',              'edit' => true],
    'driver'        => ['label' => 'Data Driver',            'edit' => true],
    'equipment'     => ['label' => 'Equipment & Operator',   'edit' => true],
    'timesheet'     => ['label' => 'Timesheet',              'edit' => true],
    'slip-gaji'     => ['label' => 'Slip Gaji',              'edit' => true],
    'data-gaji'     => ['label' => 'Data Gaji',              'edit' => true],
    'training'      => ['label' => 'Training',               'edit' => true],
    'kpi'           => ['label' => 'KPI',                    'edit' => true],
    'notifications' => ['label' => 'Notifikasi',             'edit' => false],
];
