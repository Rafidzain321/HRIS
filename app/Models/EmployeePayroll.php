<?php
// File: app/Models/EmployeePayroll.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeePayroll extends Model
{
    protected $table = 'employee_payroll';

    protected $fillable = [
        'employee_id', 'tahun', 'bulan',
        'gaji_pokok', 'tunj_tetap', 'kompensasi_pwt', 'ttt_perhari',
        'com_day', 'insentif', 'tunj_makan', 'tunj_produksi', 'tunj_lapangan',
        'tunj_kehadiran', 'tunj_pulsa', 'kompensasi_kontrak',
        'jml_jam_lembur', 'upah_lembur',
        'l_sabtu', 'l_libur', 'lembur_biasa', 'total_lembur_flat',
        'uang_hadir', 'h_kerja', 'gaji_kotor',
        'potongan_jht', 'potongan_pensiun', 'potongan_kes', 'potongan_alpa',
        'kekurangan_bulan_lalu', 'gaji_bersih',
        'izin', 'sakit', 'alpa', 'cuti','stb',
        'nama_bank', 'no_rekening', 'ptkp', 'no_bpjs_tk', 'no_bpjs_kes',
        'dibuat_oleh', 'catatan', 'tunj_jabatan',
        'h_basic', 'u_basic', 'u_kerja', 'ttt_custom', 'potongan_custom',
    ];

    protected $casts = [
        'tahun' => 'integer', 'bulan' => 'integer',
        'gaji_pokok' => 'float', 'tunj_tetap' => 'float',
        'kompensasi_pwt' => 'float', 'ttt_perhari' => 'float',
        'jml_jam_lembur' => 'float', 'upah_lembur' => 'float',
        'total_lembur_flat' => 'float', 'uang_hadir' => 'float',
        'gaji_kotor' => 'float', 'gaji_bersih' => 'float',
        'potongan_jht' => 'float', 'potongan_pensiun' => 'float',
        'potongan_kes' => 'float', 'potongan_alpa' => 'float',
        'kekurangan_bulan_lalu' => 'float',
        'tunj_kehadiran' => 'float',
        'tunj_pulsa' => 'float',
        'kompensasi_kontrak' => 'float',
        'l_sabtu' => 'integer', 'l_libur' => 'integer', 'lembur_biasa' => 'integer',
        'h_kerja' => 'integer', 'izin' => 'integer', 'sakit' => 'integer',
        'alpa' => 'integer', 'cuti' => 'integer', 'ttt_custom' => 'array',
        'potongan_custom' => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}