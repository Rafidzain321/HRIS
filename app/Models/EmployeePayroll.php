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
        'h_basic', 'u_basic', 'u_kerja',
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
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    // Helper: hitung upah lembur per jam
    public function getNilaiLemburPerJamAttribute(): float
    {
        $dul = $this->gaji_pokok + $this->tunj_tetap;
        // DUL = nilai lebih besar antara (gapok+tt) atau (gapok+tt) * 75%
        // Formula: DUL/173
        return $dul / 173;
    }
    public function toSlipArray(): array
    {
        $emp = $this->employee;

        // Susun daftar TTT (hanya yang nilainya != 0)
        $tttFields = [
            'tunj_makan'         => 'Uang Makan',
            'tunj_produksi'      => 'Tunj. Produksi',
            'tunj_lapangan'      => 'Tunj. Lapangan',
            'tunj_kehadiran'     => 'Tunj. Kehadiran',
            'tunj_pulsa'         => 'Tunj. Pulsa',
            'kompensasi_kontrak' => 'Komp. Kontrak',
            'insentif'           => 'Insentif',
            'com_day'            => 'Com Day',
        ];
        $ttt = [];
        foreach ($tttFields as $key => $label) {
            $val = (float) ($this->$key ?? 0);
            if ($val != 0.0) {
                $ttt[] = ['key' => $key, 'label' => $label, 'nilai' => $val];
            }
        }
        // TTT custom (kolom ttt_custom = array)
        foreach (($this->ttt_custom ?? []) as $key => $val) {
            if ((float) $val != 0.0) {
                $ttt[] = [
                    'key'   => $key,
                    'label' => ucwords(str_replace('_', ' ', $key)),
                    'nilai' => (float) $val,
                ];
            }
        }

        $totalPotongan = (float) $this->potongan_jht
            + (float) $this->potongan_pensiun
            + (float) $this->potongan_kes
            + (float) $this->potongan_alpa
            + (float) $this->potongan_insentif
            + (float) $this->pot_tabung_oksigen;

        return [
            'id'                  => $this->id,
            'periode'             => ['tahun' => $this->tahun, 'bulan' => $this->bulan],

            // identitas
            'id_badge'            => $emp?->id_badge,
            'nama_lengkap'        => $emp?->nama_lengkap,
            'jabatan'             => $emp?->position?->nama_jabatan ?? '-',
            'project_nama'        => $emp?->project?->nama,
            'no_rekening'         => $this->no_rekening ?? $emp?->no_rekening,
            'nama_bank'           => $this->nama_bank   ?? $emp?->nama_bank,
            'no_bpjs_tk'          => $this->no_bpjs_tk  ?? $emp?->no_bpjs_tk,
            'no_bpjs_kes'         => $this->no_bpjs_kes ?? $emp?->no_bpjs_kes,
            'ptkp'                => $this->ptkp ?? $emp?->ptkp ?? '-',
            'tanggal_masuk'       => $emp?->tanggal_masuk?->format('d M Y'),

            // perolehan
            'gaji_pokok'          => (float) $this->gaji_pokok,
            'tunj_tetap'          => (float) $this->tunj_tetap,
            'kompensasi_pwt'      => (float) $this->kompensasi_pwt,
            'ttt'                 => $ttt,
            'upah_lembur'         => (float) $this->upah_lembur,

            // total kotor — LANGSUNG dari DB
            'gaji_kotor'          => (float) $this->gaji_kotor,

            // potongan
            'potongan_jht'        => (float) $this->potongan_jht,
            'potongan_pensiun'    => (float) $this->potongan_pensiun,
            'potongan_kes'        => (float) $this->potongan_kes,
            'potongan_alpa'       => (float) $this->potongan_alpa,
            'potongan_insentif'   => (float) $this->potongan_insentif,
            'pot_tabung_oksigen'  => (float) $this->pot_tabung_oksigen,
            'total_potongan'      => $totalPotongan,
            'kekurangan_bulan_lalu' => (float) $this->kekurangan_bulan_lalu,

            // total bersih — LANGSUNG dari DB
            'gaji_bersih'         => (float) $this->gaji_bersih,

            // absensi
            'izin'  => (int) $this->izin,
            'sakit' => (int) $this->sakit,
            'alpa'  => (int) $this->alpa,
            'cuti'  => (int) $this->cuti,
        ];
    }
}