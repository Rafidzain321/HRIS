<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Training extends Model
{
    protected $fillable = [
        'employee_id','jenis_training','tanggal_training',
        'tanggal_expired','trainer','score','status','lokasi','catatan',
    ];
    protected $casts = ['tanggal_training' => 'date', 'tanggal_expired' => 'date'];
    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
}
