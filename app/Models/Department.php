<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $fillable = ['nama', 'kode', 'deskripsi', 'is_active'];

    public function employees(): HasMany  { return $this->hasMany(Employee::class); }
    public function positions(): HasMany  { return $this->hasMany(Position::class); }
}
