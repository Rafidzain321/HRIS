<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CcpmManpower extends Model
{
    public function project()
    {
        return $this->belongsTo(\App\Models\Project::class);
    }
    protected $table = 'ccpm_manpower';
    protected $fillable = [
        'project_id',
        'badge',
        'id_card',
        'hes_passport',
        'name',
        'birth_place',
        'birth_date',
        'ffd_valid_date',
        'badge_valid_date',
        'job_title',
        'team_assignment',
        'status',
        'status_medical',
    ];
    protected $casts = [
        'birth_date' => 'date',
        'ffd_valid_date' => 'date',
        'badge_valid_date' => 'date',
    ];

    public function scopeComplete($q)
    {
        return $q->where('status', 'Complete');
    }
    public function scopeInProgress($q)
    {
        return $q->where('status', 'like', 'In Progress%');
    }
    public function scopeReject($q)
    {
        return $q->where('status', 'like', 'Reject%');
    }
}
