<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingType extends Model
{
    protected $fillable = [
        'nama', 'deskripsi', 'masa_berlaku_tahun',
        'has_nilai', 'has_expired', 'is_active', 'urutan',
    ];

    protected $casts = [
        'has_nilai'   => 'boolean',
        'has_expired' => 'boolean',
        'is_active'   => 'boolean',
    ];

    public function trainings(): HasMany
    {
        return $this->hasMany(EmployeeTraining::class);
    }

    public function getMasaBerlakuLabelAttribute(): string
    {
        if (!$this->masa_berlaku_tahun) return 'Seumur Hidup';
        return $this->masa_berlaku_tahun . ' Tahun';
    }
}
