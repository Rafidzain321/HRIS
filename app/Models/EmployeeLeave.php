<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class EmployeeLeave extends Model
{
    // Jenis yang benar-benar "Cuti" (dihitung sebagai absen resmi di kolom Cuti pada halaman
    // Kehadiran) — beda dari jenis "Izin" (izin_tanpa_potong, izin_tanpa_upah) yang punya
    // kolom Izin sendiri di Kehadiran supaya tidak dobel hitung.
    const JENIS_CUTI = ['cuti_tahunan', 'cuti_haji', 'cuti_umroh', 'cuti_bersalin', 'cuti_haid'];

    protected $fillable = [
        'employee_id', 'jenis', 'kategori_izin', 'tanggal_mulai', 'tanggal_selesai',
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
    // rentang bulan yang diminta sebelum dihitung hari kerjanya. Cuma jenis "Cuti" (JENIS_CUTI)
    // yang dihitung — jenis "Izin" (izin_tanpa_potong/izin_tanpa_upah) punya kolom Izin sendiri
    // di Kehadiran, supaya tidak dobel hitung.
    public static function hariCutiDalamBulan(int $employeeId, int $tahun, int $bulan): int
    {
        $awalBulan   = Carbon::create($tahun, $bulan, 1)->startOfMonth();
        $akhirBulan  = $awalBulan->copy()->endOfMonth();

        $leaves = static::where('employee_id', $employeeId)
            ->whereIn('jenis', self::JENIS_CUTI)
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

    // "Cuti Berlebih" — kalau tahun lalu karyawan pakai cuti_tahunan melebihi jatah, kelebihannya
    // mengurangi jatah tahun ini (dibatasi $maksBawaKeDepan supaya tidak minus tak terbatas).
    public static function jatahEfektif(int $employeeId, int $tahun, int $jatahTahunan, int $maksBawaKeDepan): int
    {
        $terpakaiTahunLalu = static::where('employee_id', $employeeId)
            ->where('jenis', 'cuti_tahunan')
            ->whereYear('tanggal_mulai', $tahun - 1)
            ->sum('jumlah_hari');

        $kelebihanTahunLalu = max(0, $terpakaiTahunLalu - $jatahTahunan);
        $potongan = min($kelebihanTahunLalu, $maksBawaKeDepan);

        return $jatahTahunan - $potongan;
    }

    // Cuti Haji/Umroh cuma boleh dipakai 1 kali seumur bekerja di perusahaan ini (Pasal 25 PP).
    public static function sudahPernahPakai(int $employeeId, string $jenis): bool
    {
        return static::where('employee_id', $employeeId)->where('jenis', $jenis)->exists();
    }

    // Izin Tanpa Upah dibatasi maksimal 5 hari kerja per tahun (Pasal 27 PP).
    public static function totalHariTahunIni(int $employeeId, string $jenis, int $tahun): int
    {
        return (int) static::where('employee_id', $employeeId)
            ->where('jenis', $jenis)
            ->whereYear('tanggal_mulai', $tahun)
            ->sum('jumlah_hari');
    }
}
