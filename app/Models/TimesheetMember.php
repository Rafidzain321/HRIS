<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimesheetMember extends Model
{
    protected $table    = 'timesheet_members';
    protected $fillable = [
        'id_badge',
        'project_id',
        'tipe',
        'sub_group',
        'nama_override',
        'urutan',
        'aktif',
        'kelompok',
    ];
    protected $casts = ['aktif' => 'boolean'];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'id_badge', 'id_badge');
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}