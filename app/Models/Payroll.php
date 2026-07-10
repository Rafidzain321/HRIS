<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payroll extends Model
{
    protected $fillable = [
        'employee_id','no_contract','bulan','tahun','project',
        'gapok','t_jabatan','incentive','uang_makan','produksi',
        'lapangan','lembur_sabtu','lembur_minggu','uang_transport',
        'kompensasi','total_pendapatan','status','tanggal_bayar','dibuat_oleh',
    ];
    protected $casts = ['tanggal_bayar' => 'date'];

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }

    public function getTotalPendapatanAttribute(): float
    {
        return $this->gapok + $this->t_jabatan + $this->incentive
             + $this->uang_makan + $this->produksi + $this->lapangan
             + $this->lembur_sabtu + $this->lembur_minggu
             + $this->uang_transport + $this->kompensasi;
    }
}
