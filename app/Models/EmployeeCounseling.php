<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeCounseling extends Model
{
    protected $fillable = [
        'employee_id', 'tanggal_konseling', 'kategori', 'catatan',
        'tindak_lanjut', 'status', 'ditangani_oleh',
    ];

    protected $casts = [
        'tanggal_konseling' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
