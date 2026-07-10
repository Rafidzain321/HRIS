<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplianceLog extends Model
{
    protected $fillable = [
        'employee_id','tipe','status_lama','status_baru',
        'expired_lama','expired_baru','catatan','updated_by',
    ];
    protected $casts = ['expired_lama' => 'date', 'expired_baru' => 'date'];
    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
}
