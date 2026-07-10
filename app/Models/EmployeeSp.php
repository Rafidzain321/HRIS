<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeSp extends Model
{
    protected $table    = 'employee_sp';
    protected $fillable = ['employee_id','tipe_sp','tanggal_sp','alasan','catatan','dibuat_oleh'];
    protected $casts    = ['tanggal_sp' => 'date'];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}