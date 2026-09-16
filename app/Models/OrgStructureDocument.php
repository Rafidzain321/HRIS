<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrgStructureDocument extends Model
{
    protected $fillable = ['nama_file', 'path', 'mime_type', 'size', 'uploaded_by'];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getSizeFormattedAttribute(): string
    {
        $size = $this->size;
        if ($size >= 1048576) return round($size / 1048576, 1) . ' MB';
        if ($size >= 1024) return round($size / 1024, 1) . ' KB';
        return $size . ' B';
    }
}
