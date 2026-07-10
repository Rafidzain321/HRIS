<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    protected $fillable = ['tanggal', 'keterangan', 'tipe'];
    protected $casts    = ['tanggal' => 'date'];

    // Scope: ambil hari libur dalam bulan tertentu
    public function scopeInMonth($query, $tahun, $bulan)
    {
        return $query->whereYear('tanggal', $tahun)->whereMonth('tanggal', $bulan);
    }

    // Tipe labels
    public static function tipeOptions(): array
    {
        return [
            'libur_nasional' => 'Libur Nasional',
            'cuti_bersama'   => 'Cuti Bersama',
            'libur_khusus'   => 'Libur Khusus',
        ];
    }
}
