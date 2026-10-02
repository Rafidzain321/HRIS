<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

// Project riil (kode kontrak/pekerjaan, mis. "AKM-PP") — beda dari Project (kantor/payroll).
// Satu project bisa terhubung ke beberapa kantor (pivot client_project_kantor); satu kantor bisa punya banyak project.
class ClientProject extends Model
{
    protected $fillable = ['kode', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'employee_client_project');
    }

    public function kantors(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'client_project_kantor')->withTimestamps();
    }
}
