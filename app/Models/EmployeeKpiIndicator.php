<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeKpiIndicator extends Model
{
    protected $fillable = ['employee_id', 'nama_indikator', 'bobot', 'urutan', 'aktif'];

    protected $casts = [
        'bobot'  => 'float',
        'urutan' => 'integer',
        'aktif'  => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(EmployeeKpiScore::class, 'indicator_id');
    }
}
