<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CcpmManpower extends Model
{
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

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
