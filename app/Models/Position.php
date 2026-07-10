<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Position extends Model
{
    protected $fillable = ['nama_jabatan','level','department_id','gaji_pokok_min','gaji_pokok_max','is_active'];

    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function employees(): HasMany    { return $this->hasMany(Employee::class); }
}
