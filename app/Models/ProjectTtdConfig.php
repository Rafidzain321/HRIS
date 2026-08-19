<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectTtdConfig extends Model
{
    protected $table = 'project_ttd_configs';

    protected $fillable = [
        'project_id', 'ttd_list', 'dibuat_oleh',
    ];

    protected $casts = [
        'ttd_list' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
