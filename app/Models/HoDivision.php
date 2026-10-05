<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Divisi Head Office (Finance, HR, IT, dst) — dipakai Pengajuan Training (kuota per divisi).
class HoDivision extends Model
{
    protected $fillable = ['nama', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function hoDetails(): HasMany
    {
        return $this->hasMany(EmployeeHoDetail::class);
    }

    public function trainingRequests(): HasMany
    {
        return $this->hasMany(TrainingRequest::class);
    }
}
