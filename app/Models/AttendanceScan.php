<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceScan extends Model
{
    protected $fillable = [
        'machine_user_id', 'tanggal', 'jam_kerja', 'scan_masuk', 'scan_pulang',
        'pengecualian', 'waktu_scan', 'kategori', 'keterangan',
    ];

    protected $casts = ['tanggal' => 'date'];

    public function machineUser(): BelongsTo { return $this->belongsTo(AttendanceMachineUser::class, 'machine_user_id'); }
}
