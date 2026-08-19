<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeKpiScore extends Model
{
    protected $fillable = ['indicator_id', 'tahun', 'semester', 'skor', 'catatan', 'dibuat_oleh'];

    protected $casts = [
        'tahun'    => 'integer',
        'semester' => 'integer',
        'skor'     => 'float',
    ];

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(EmployeeKpiIndicator::class, 'indicator_id');
    }
}
