<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'id_badge','nama_lengkap','no_ktp','no_telepon','tempat_lahir',
        'tanggal_lahir','alamat','kota_asal','agama','tamatan','jenis_kelamin',
        'position_id','department_id','group','status','rfid','foto','foto_profil',
        'expire_badge','status_kp','kp_ready','exp_kp',
        'type_sim','no_sim','sim_kota_keluar','expired_sim',
        'sio_k3','no_sio','expire_sio','nama_perusahaan_sio','tipe_sio',
        'ccpm','hes_passport',
        'tgl_mcu','exp_mcu','status_mcu','lokasi_mcu',
        'ukuran_baju','ukuran_sepatu', 'nama_ibu', 'ptkp', 'tanggal_masuk',
        'tanggal_keluar', 'alasan_keluar', 'catatan_keluar',
        'derajat_kesehatan', 'insentif',
        'tanggal_hi','nama_trainer_hi','swp_pt_ha','nama_trainer_swp',
        'hasil_posttest_swp','status_posttest_pwtha','post_test_mvshe','spotter_flagman',
        'start_pkwt','end_pkwt','bln_pkwt','no_contract','no_rekening',
        'no_bpjs_tk','no_bpjs_kes','nama_bank',
        'disnaker','project_id',
    ];

    protected $casts = [
        'tanggal_lahir'  => 'date',
        'expire_badge'   => 'date',
        'exp_kp'         => 'date',
        'expired_sim'    => 'date',
        'expire_sio'     => 'date',
        'tgl_mcu'        => 'date',
        'exp_mcu'        => 'date',
        'tanggal_hi'     => 'date',
        'swp_pt_ha'      => 'date',
        'start_pkwt'     => 'date',
        'end_pkwt'       => 'date',
        'tanggal_masuk'  => 'date',
        'tanggal_keluar' => 'date',
    ];

    // ── RELASI ──
    public function position(): BelongsTo    { return $this->belongsTo(Position::class); }
    public function department(): BelongsTo  { return $this->belongsTo(Department::class); }
    public function ppe(): HasOne            { return $this->hasOne(Ppe::class); }
    public function trainings(): HasMany     { return $this->hasMany(Training::class); }
    public function documents(): HasMany     { return $this->hasMany(EmployeeDocument::class); }
    public function timesheets(): HasMany    { return $this->hasMany(Timesheet::class); }
    public function project() { return $this->belongsTo(Project::class); }
    public function hoDetail(): HasOne       { return $this->hasOne(EmployeeHoDetail::class); }
    public function kpiIndicators(): HasMany { return $this->hasMany(EmployeeKpiIndicator::class); }
    // ── COMPUTED ATTRIBUTES ──
    public function getUmurAttribute(): ?int
    {
        return $this->tanggal_lahir ? $this->tanggal_lahir->age : null;
    }

    public function getSimStatusAttribute(): string
    {
        if (!$this->expired_sim) return 'na';
        $days = now()->diffInDays($this->expired_sim, false);
        if ($days < 0)  return 'expired';
        if ($days <= 30) return 'warning';
        return 'valid';
    }

    public function getMcuStatusAttribute(): string
    {
        if (!$this->exp_mcu) return 'na';
        $days = now()->diffInDays($this->exp_mcu, false);
        if ($days < 0)  return 'expired';
        if ($days <= 30) return 'warning';
        return 'valid';
    }

    public function getBadgeStatusAttribute(): string
    {
        if (!$this->expire_badge) return 'na';
        $days = now()->diffInDays($this->expire_badge, false);
        if ($days < 0)  return 'expired';
        if ($days <= 30) return 'warning';
        return 'valid';
    }

    public function getSioStatusAttribute(): string
    {
        if (!$this->expire_sio) return 'na';
        if ($this->expire_sio->isPast()) return 'expired';
        return 'valid';
    }

    // ── SCOPES ──
    public function scopeAktif($query)       { return $query->where('status', 'AKTIF'); }
    public function scopeSimExpired($query)  { return $query->whereNotNull('expired_sim')->where('expired_sim', '<', now()); }
    public function scopeMcuExpired($query)  { return $query->whereNotNull('exp_mcu')->where('exp_mcu', '<', now()); }
    public function scopeBadgeExpired($query){ return $query->whereNotNull('expire_badge')->where('expire_badge', '<', now()); }
    public function scopeBadgeWarning($query){ return $query->whereNotNull('expire_badge')->whereBetween('expire_badge', [now(), now()->addDays(30)]); }
    public function scopeSimWarning($query)  { return $query->whereNotNull('expired_sim')->whereBetween('expired_sim', [now(), now()->addDays(30)]); }
}
