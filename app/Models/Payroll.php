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
}
