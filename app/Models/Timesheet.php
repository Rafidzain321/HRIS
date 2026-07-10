<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Timesheet extends Model
{
    protected $fillable = ['employee_id','tahun','bulan','hari','nilai'];

    protected $casts = ['tahun'=>'integer','bulan'=>'integer','hari'=>'integer'];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
