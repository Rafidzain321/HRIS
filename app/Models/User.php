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
        'is_active',
        'last_login_at',
        'restrict_payroll',
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
        ];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
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
