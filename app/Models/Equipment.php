<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Equipment extends Model
{
    protected $table = 'equipments';
    protected $fillable = [
        'project_id',  // ← tambah
        'no_unit','plat_nomor','type_unit','model','manufacture','serial_no',
        'tahun','gps_unit_id','kategori','kapasitas',
        'stnk_expired','tax_expired','kir_expired','izin_non_bm_expired',
        'vehicle_pass_expired','inspection_date','smbr_pass_expired','green_stiker_expired',
        'sio_migas_no','sio_migas_expired','sio_disnaker_expired',
        'k3_p3a2_no','k3_p3a2_expired',
        'tpe_cem_inspector','contractor_cem_inspector','location_of_inspection',
        'status','keterangan',
    ];

    protected $casts = [
        'stnk_expired'         => 'date',
        'tax_expired'          => 'date',
        'kir_expired'          => 'date',
        'izin_non_bm_expired'  => 'date',
        'vehicle_pass_expired' => 'date',
        'inspection_date'      => 'date',
        'smbr_pass_expired'    => 'date',
        'green_stiker_expired' => 'date',
        'sio_migas_expired'    => 'date',
        'sio_disnaker_expired' => 'date',
        'k3_p3a2_expired'      => 'date',
    ];

    public function project(): BelongsTo {
        return $this->belongsTo(Project::class);
    }

    public function operators(): HasMany {
        return $this->hasMany(EquipmentOperator::class);
    }

    public function activeOperator(): HasOne {
        return $this->hasOne(EquipmentOperator::class)->where('is_active', true)->latestOfMany();
    }

    public function getStnkStatusAttribute(): string {
        if (!$this->stnk_expired) return 'na';
        if ($this->stnk_expired->isPast()) return 'expired';
        if ($this->stnk_expired->diffInDays(now()) <= 30) return 'warning';
        return 'valid';
    }

    public function getKirStatusAttribute(): string {
        if (!$this->kir_expired) return 'na';
        if ($this->kir_expired->isPast()) return 'expired';
        if ($this->kir_expired->diffInDays(now()) <= 30) return 'warning';
        return 'valid';
    }

    public function getVehiclePassStatusAttribute(): string {
        if (!$this->vehicle_pass_expired) return 'na';
        if ($this->vehicle_pass_expired->isPast()) return 'expired';
        if ($this->vehicle_pass_expired->diffInDays(now()) <= 30) return 'warning';
        return 'valid';
    }
}