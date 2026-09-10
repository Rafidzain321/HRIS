<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeAttendance extends Model
{
    protected $fillable = [
        'employee_id', 'tahun', 'bulan', 'hadir', 'izin', 'sakit', 'alpha', 'dicatat_oleh',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
