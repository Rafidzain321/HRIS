<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class DriverDetail extends Model
{
    protected $table = 'drivers_detail';
    protected $fillable = [
        'project_id',
        'name',
        'id_card',
        'badge',
        'license_type',
        'license_no',
        'rfid',
        'posttest_schedule',
        'driver_status',
        'permit_expired_date',
        'posttest_schedule_status',
        'posttest_status',
        'date_approve_posttest',
        'dvp_status',
    ];
    protected $casts = [
        'posttest_schedule' => 'date',
        'permit_expired_date' => 'date',
        'date_approve_posttest' => 'date',
    ];

    public function getPermitStatusAttribute(): string
    {
        if (!$this->permit_expired_date)
            return 'na';
        if ($this->permit_expired_date->isPast())
            return 'expired';
        if ($this->permit_expired_date->diffInDays(now()) <= 30)
            return 'warning';
        return 'valid';
    }
}
