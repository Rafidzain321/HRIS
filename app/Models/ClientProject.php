<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

// Project riil (kode kontrak/pekerjaan, mis. "AKM-PP") — beda dari Project (kantor/payroll).
class ClientProject extends Model
{
    protected $fillable = ['kode', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'employee_client_project');
    }
}
