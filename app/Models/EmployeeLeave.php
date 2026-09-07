<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class EmployeeLeave extends Model
{
    protected $fillable = [
        'employee_id', 'tanggal_mulai', 'tanggal_selesai',
        'jumlah_hari', 'keterangan', 'dicatat_oleh',
    ];

    protected $casts = [
        'tanggal_mulai'   => 'date',
        'tanggal_selesai' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    // Hitung jumlah hari kerja (Senin-Jumat, di luar tanggal yang terdaftar di tabel holidays)
    // dalam rentang tanggal_mulai s/d tanggal_selesai (inklusif) — dipakai buat memotong jatah
    // cuti tahunan. Sabtu & Minggu serta hari libur tidak ikut dihitung.
    public static function hitungHariKerja(string $tanggalMulai, string $tanggalSelesai): int
    {
        $mulai   = Carbon::parse($tanggalMulai);
        $selesai = Carbon::parse($tanggalSelesai);
        if ($selesai->lt($mulai)) return 0;

        $liburDates = Holiday::whereBetween('tanggal', [$mulai->toDateString(), $selesai->toDateString()])
            ->pluck('tanggal')->map(fn ($d) => $d->toDateString())->flip();

        $hari = 0;
        for ($d = $mulai->copy(); $d->lte($selesai); $d->addDay()) {
            if ($d->isWeekend()) continue; // Sabtu & Minggu
            if (isset($liburDates[$d->toDateString()])) continue;
            $hari++;
        }
        return $hari;
    }
}
