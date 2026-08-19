<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeHoDetail extends Model
{
    protected $table = 'employee_ho_details';

    protected $fillable = [
        'employee_id', 'unit', 'nik_ho', 'lokasi_kerja', 'status_karyawan',
        'nama_ktp', 'no_kk', 'rt_rw', 'kelurahan', 'kecamatan', 'propinsi',
        'npwp', 'email',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
