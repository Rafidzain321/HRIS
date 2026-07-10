<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentOperator extends Model
{
    protected $fillable = [
        'project_id',
        'equipment_id',
        'employee_id',
        'operator_name',
        'badge',
        'license_no',
        'license_expired_date',
        'rfid',
        'kp_no',
        'kp_expired_date',
        'cdrive_expired_date',
        'postest_expired_date',
        'permit_no',
        'permit_expired_date',
        'sio_migas_no',
        'sio_migas_expired',
        'sio_disnaker_expired',
        'k3_p3a2_no',
        'k3_p3a2_expired',
        'is_active',
    ];

    protected $casts = [
        'license_expired_date' => 'date',
        'kp_expired_date'      => 'date',
        'cdrive_expired_date'  => 'date',
        'postest_expired_date' => 'date',
        'permit_expired_date'  => 'date',
        'sio_migas_expired'    => 'date',
        'sio_disnaker_expired' => 'date',
        'k3_p3a2_expired'      => 'date',
        'is_active'            => 'boolean',
    ];

    public function project(): BelongsTo {
        return $this->belongsTo(Project::class);
    }

    public function equipment(): BelongsTo {
        return $this->belongsTo(Equipment::class);
    }

    public function employee(): BelongsTo {
        return $this->belongsTo(Employee::class);
    }

    public function getLicenseStatusAttribute(): string {
        if (!$this->license_expired_date) return 'na';
        if ($this->license_expired_date->isPast()) return 'expired';
        if (now()->diffInDays($this->license_expired_date, false) <= 30) return 'warning';
        return 'valid';
    }

    public function getKpStatusAttribute(): string {
        if (!$this->kp_expired_date) return 'na';
        if ($this->kp_expired_date->isPast()) return 'expired';
        if (now()->diffInDays($this->kp_expired_date, false) <= 30) return 'warning';
        return 'valid';
    }

    public function getPermitStatusAttribute(): string {
        if (!$this->permit_expired_date) return 'na';
        if ($this->permit_expired_date->isPast()) return 'expired';
        if (now()->diffInDays($this->permit_expired_date, false) <= 30) return 'warning';
        return 'valid';
    }
}