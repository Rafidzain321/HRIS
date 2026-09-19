<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiCriteria extends Model
{
    protected $table = 'kpi_criteria';

    protected $fillable = ['section', 'sub_kategori', 'urutan', 'deskripsi', 'employee_id', 'created_by', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
