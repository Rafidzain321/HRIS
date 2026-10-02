<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    // Project riil (Data Project) yang terhubung ke kantor ini — satu project bisa di banyak kantor.
    public function clientProjects(): BelongsToMany
    {
        return $this->belongsToMany(ClientProject::class, 'client_project_kantor')->withTimestamps();
    }
}