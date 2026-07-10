<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectTttItem extends Model
{
    protected $table = 'project_ttt_items';

    protected $fillable = [
        'project_id', 'key', 'label', 'urutan', 'aktif', 'is_default',
    ];

    protected $casts = [
        'aktif'      => 'boolean',
        'is_default' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}