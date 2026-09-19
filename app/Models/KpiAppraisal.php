<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KpiAppraisal extends Model
{
    protected $fillable = [
        'employee_id', 'reviewer_id', 'tahun', 'semester',
        'nilai_a', 'nilai_b', 'total_nilai', 'predikat', 'catatan',
        'status', 'submitted_at',
    ];

    protected $casts = [
        'nilai_a'      => 'float',
        'nilai_b'      => 'float',
        'total_nilai'  => 'float',
        'submitted_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reviewer_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(KpiAppraisalScore::class);
    }

    // K (0-59) / C (60-75) / B (76-95) / BS (96-99) / A (100) — sesuai form penilaian resmi.
    public static function predikatDari(?float $total): ?string
    {
        if ($total === null) return null;
        if ($total >= 100) return 'A';
        if ($total >= 96) return 'BS';
        if ($total >= 76) return 'B';
        if ($total >= 60) return 'C';
        return 'K';
    }

    public static function predikatLabel(?string $kode): ?string
    {
        return match ($kode) {
            'A' => 'Memuaskan',
            'BS' => 'Baik Sekali',
            'B' => 'Baik',
            'C' => 'Cukup',
            'K' => 'Kurang',
            default => null,
        };
    }
}
