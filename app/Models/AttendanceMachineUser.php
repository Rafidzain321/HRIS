<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceMachineUser extends Model
{
    protected $fillable = ['lokasi', 'no_id', 'nama_mesin', 'employee_id'];

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function scans(): HasMany      { return $this->hasMany(AttendanceScan::class, 'machine_user_id'); }
}
