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

    // Total hari kerja cuti seorang karyawan yang jatuh di bulan tertentu — dipakai fitur
    // Kehadiran (Attendance) supaya nilai "Cuti" selalu diambil dari sini, bukan diketik ulang.
    // Satu catatan cuti bisa melewati batas bulan (mis. 29 Des - 3 Jan), jadi dipotong dulu ke
    // rentang bulan yang diminta sebelum dihitung hari kerjanya.
    public static function hariCutiDalamBulan(int $employeeId, int $tahun, int $bulan): int
    {
        $awalBulan   = Carbon::create($tahun, $bulan, 1)->startOfMonth();
        $akhirBulan  = $awalBulan->copy()->endOfMonth();

        $leaves = static::where('employee_id', $employeeId)
            ->where('tanggal_mulai', '<=', $akhirBulan)
            ->where('tanggal_selesai', '>=', $awalBulan)
            ->get(['tanggal_mulai', 'tanggal_selesai']);

        $total = 0;
        foreach ($leaves as $leave) {
            $mulai   = $leave->tanggal_mulai->max($awalBulan);
            $selesai = $leave->tanggal_selesai->min($akhirBulan);
            $total  += static::hitungHariKerja($mulai->toDateString(), $selesai->toDateString());
        }
        return $total;
    }
}
