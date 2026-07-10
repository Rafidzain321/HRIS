<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ppe extends Model
{
    protected $table    = 'ppe';
    protected $fillable = [
        'employee_id','frc','safety_shoes',
        'safety_glass','safety_vest','ear_plug',
        'tanggal_distribusi','catatan',
        // FRC tanggal (4 kolom: 2 di 2024, 1 di 2025, 1 di 2026)
        'tgl_frc','tgl_frc_2','tgl_frc_3','tgl_frc_4',
        // Sepatu tanggal (3 kolom: 2024, 2025, 2026)
        'tgl_sepatu','tgl_sepatu_2','tgl_sepatu_3',
        // Helmet (2 tanggal: putih + orange)
        'tgl_helm','tgl_helm_orange',
        // Safety Glass (2 tanggal)
        'tgl_glass','tgl_glass_2',
        // Safety Vest (1 tanggal)
        'tgl_vest',
        // Ear Plug (2 tanggal)
        'tgl_ear_plug','tgl_ear_plug_2','white_helmet','helmet',
    ];
    protected $casts = [
        'tanggal_distribusi' => 'date',
        'tgl_frc'        => 'date',
        'tgl_frc_2'      => 'date',
        'tgl_frc_3'      => 'date',
        'tgl_frc_4'      => 'date',
        'tgl_sepatu'     => 'date',
        'tgl_sepatu_2'   => 'date',
        'tgl_sepatu_3'   => 'date',
        'tgl_helm'      => 'date',
        'tgl_helm_orange' => 'date',
        'tgl_glass'      => 'date',
        'tgl_glass_2'    => 'date',
        'tgl_vest'       => 'date',
        'tgl_ear_plug'   => 'date',
        'tgl_ear_plug_2' => 'date',
        'safety_glass'   => 'boolean',
        'safety_vest'    => 'boolean',
        'ear_plug'       => 'boolean',
        'white_helmet'   => 'boolean',
        'helmet'         => 'boolean',
    ];
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}