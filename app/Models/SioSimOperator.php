<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SioSimOperator extends Model
{
    protected $table = 'sio_sim_operators';
    protected $fillable = ['no','nama','license_expired','kp_expired','sio_expired','keterangan'];
    protected $casts = ['license_expired'=>'date','kp_expired'=>'date','sio_expired'=>'date'];

    public function getLicenseStatusAttribute(): string
    {
        if (!$this->license_expired) return 'na';
        if ($this->license_expired->isPast()) return 'expired';
        if ($this->license_expired->diffInDays(now()) <= 30) return 'warning';
        return 'valid';
    }
    public function getKpStatusAttribute(): string
    {
        if (!$this->kp_expired) return 'na';
        if ($this->kp_expired->isPast()) return 'expired';
        if ($this->kp_expired->diffInDays(now()) <= 30) return 'warning';
        return 'valid';
    }
    public function getSioStatusAttribute(): string
    {
        if (!$this->sio_expired) return 'na';
        if ($this->sio_expired->isPast()) return 'expired';
        return 'valid';
    }
}
