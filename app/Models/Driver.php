<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Driver extends Model
{
    protected $fillable = [
        'employee_id','license_type','license_no','rfid',
        'permit_expired_date','posttest_schedule','driver_status',
        'posttest_schedule_status','posttest_status','date_approve_posttest','dvp_status',
    ];
    protected $casts = [
        'permit_expired_date'   => 'date',
        'posttest_schedule'     => 'date',
        'date_approve_posttest' => 'date',
    ];
    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
}
