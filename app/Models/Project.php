<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = [
        'kode', 'nama', 'lokasi',
        'tipe_timesheet', 'tipe_gaji',
        'warna', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function timesheetMembers(): HasMany
    {
        return $this->hasMany(TimesheetMember::class);
    }
}