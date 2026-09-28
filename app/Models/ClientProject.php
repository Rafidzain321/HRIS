<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

// Project riil (kode kontrak/pekerjaan, mis. "AKM-PP") — beda dari Project (kantor/payroll).
// Tiap project dimiliki satu kantor (project_id -> tabel projects); satu kantor bisa punya banyak project.
class ClientProject extends Model
{
    protected $fillable = ['kode', 'project_id', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'employee_client_project');
    }

    public function kantor(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
}
