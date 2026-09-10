<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pph21TerBracket extends Model
{
    protected $fillable = ['kategori', 'batas_atas', 'tarif_persen', 'urutan'];

    protected $casts = [
        'batas_atas'   => 'integer',
        'tarif_persen' => 'float',
    ];
}
