<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OvertimeCustom extends Model
{
    protected $table = 'overtime_custom';

    protected $fillable = [
        'employee_id', 'project_id', 'tahun', 'bulan',
        'kategori', 'tarif_per_hari', 'jumlah_hari',
    ];

    protected $casts = [
        'tarif_per_hari' => 'integer',
        'jumlah_hari'    => 'integer',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}