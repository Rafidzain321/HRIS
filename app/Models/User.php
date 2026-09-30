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
        'project_id',
        'project_ids',
        'full_edit_project_ids',
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

    // Gabungan project_id (project utama) + full_edit_project_ids (project tambahan yang juga
    // boleh di-edit penuh) — dipakai buat cek readonly di HandleInertiaRequests & CheckMenuPermission
    // supaya user yang memang mengelola lebih dari satu project tidak dibikin read-only.
    public function fullEditProjectIds(): array
    {
        $extra = $this->full_edit_project_ids ? json_decode($this->full_edit_project_ids, true) : [];
        return array_values(array_unique(array_filter([(int) $this->project_id, ...array_map('intval', $extra ?: [])])));
    }

    // Kantor yang data karyawannya boleh diakses user. null = semua kantor.
    // $edit=true: cuma kantor full-edit (kantor lihat-saja tidak boleh diubah).
    public function aksesKantor(bool $edit = false): ?array
    {
        if ($this->hasRole('super-admin')) return null;
        if ($this->hasRole('viewer')) return $edit ? [] : null;

        $lihat = $this->project_ids ? array_map('intval', json_decode($this->project_ids, true) ?: []) : [];
        // Akun tanpa kantor sama sekali (mis. HR Staff) = semua kantor, konsisten dengan Controller::activeProjectId().
        if (!$this->project_id && !$lihat) return null;

        return $edit ? $this->fullEditProjectIds() : array_values(array_unique([...$this->fullEditProjectIds(), ...$lihat]));
    }
}
