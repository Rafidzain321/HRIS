<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SioSimOperator extends Model
{
    protected $table = 'sio_sim_operators';
    protected $fillable = ['no','nama','license_expired','kp_expired','sio_expired','keterangan'];
    protected $casts = ['license_expired'=>'date','kp_expired'=>'date','sio_expired'=>'date'];
}
