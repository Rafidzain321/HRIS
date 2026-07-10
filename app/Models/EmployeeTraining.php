<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class EmployeeTraining extends Model
{
    protected $fillable = [
        'employee_id', 'training_type_id', 'tanggal',
        'nama_trainer', 'nilai', 'status', 'expired_date',
        'catatan', 'input_by',
    ];

    protected $casts = [
        'tanggal'      => 'date',
        'expired_date' => 'date',
    ];

    public function employee(): BelongsTo    { return $this->belongsTo(Employee::class); }
    public function trainingType(): BelongsTo { return $this->belongsTo(TrainingType::class); }
    public function inputBy(): BelongsTo     { return $this->belongsTo(User::class, 'input_by'); }

    // Auto hitung expired dari tanggal training + masa berlaku
    public function getAutoExpiredAttribute(): ?string
    {
        if ($this->expired_date) return $this->expired_date->format('Y-m-d');
        if (!$this->tanggal || !$this->trainingType?->masa_berlaku_tahun) return null;
        return $this->tanggal->addYears($this->trainingType->masa_berlaku_tahun)->format('Y-m-d');
    }

    public function getStatusExpiredAttribute(): string
    {
        $exp = $this->auto_expired;
        if (!$exp) {
            if ($this->trainingType?->masa_berlaku_tahun) return 'no_date';
            return 'lifetime';
        }
        $days = now()->diffInDays($exp, false);
        if ($days < 0)  return 'expired';
        if ($days <= 30) return 'warning';
        return 'valid';
    }

    public function getSisaHariAttribute(): ?int
    {
        $exp = $this->auto_expired;
        if (!$exp) return null;
        return (int) now()->diffInDays($exp, false);
    }
}
