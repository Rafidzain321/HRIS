<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectBpjsConfig extends Model
{
    protected $table = 'project_bpjs_configs';

    protected $fillable = [
        'project_id', 'berlaku_mulai', 'pct_jht', 'pct_pensiun', 'pct_kes', 'dibuat_oleh',
    ];

    protected $casts = [
        'berlaku_mulai' => 'date',
        'pct_jht'       => 'float',
        'pct_pensiun'   => 'float',
        'pct_kes'       => 'float',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
