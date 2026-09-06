<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'plain_password',
        'project_id',
        'project_ids',
        'employee_id',
        'is_active',
        'last_login_at',
        'restrict_payroll',
        'restrict_activity_log',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at'     => 'datetime',
            'password'          => 'hashed',
            'restrict_payroll'  => 'boolean',
            'restrict_activity_log' => 'boolean',
        ];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    // Karyawan (data HR) pemilik akun login ini — dipakai fitur self-input KPI supaya user
    // cuma bisa edit KPI dirinya sendiri, bukan orang lain.
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    // Helper: apakah user bisa akses project ini?
    public function canAccessProject(int $projectId): bool
    {
        if ($this->hasRole('super-admin'))
            return true;
        if ($this->hasRole('viewer'))
            return true;
        return $this->project_id === $projectId;
    }
}
