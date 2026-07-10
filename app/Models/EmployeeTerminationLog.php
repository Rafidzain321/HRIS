<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeTerminationLog extends Model
{
    protected $table    = 'employee_termination_logs';
    protected $fillable = ['employee_id','tanggal_keluar','alasan_keluar','catatan_keluar','dicatat_oleh'];
    protected $casts    = ['tanggal_keluar' => 'date'];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}