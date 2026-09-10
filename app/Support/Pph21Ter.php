<?php

namespace App\Support;

use App\Models\Pph21TerBracket;

/**
 * PPh21 bulanan pakai TER (Tarif Efektif Rata-rata) — PP 58/2023 & PMK 168/2023.
 * Di PT. AKM ini sifatnya CATATAN SAJA (metode Netto): pajaknya ditanggung penuh
 * oleh perusahaan, jadi TIDAK dikurangkan dari gaji_bersih karyawan di manapun.
 * Cuma dipakai sebagai info di slip gaji.
 *
 * Tabel bracket-nya disimpan di tabel `pph21_ter_brackets` (bisa diatur lewat halaman
 * "Konfigurasi PPh21 (TER)") — bukan lagi di-hardcode di sini, supaya HR/finance bisa
 * mengoreksi sendiri kalau ada baris yang meleset dari lampiran resmi PMK 168/2023.
 */
class Pph21Ter
{
    // Cache in-memory per request — supaya hitung banyak slip sekaligus (payroll bulanan)
    // tidak query tabel bracket berulang-ulang untuk tiap karyawan.
    private static ?array $cache = null;

    // Dipanggil setelah admin mengubah tabel bracket lewat halaman konfigurasi, supaya
    // permintaan berikutnya baca ulang dari DB (bukan cache lama).
    public static function clearCache(): void
    {
        self::$cache = null;
    }

    private static function loadBrackets(): array
    {
        if (self::$cache !== null) return self::$cache;

        self::$cache = ['A' => [], 'B' => [], 'C' => []];
        foreach (Pph21TerBracket::orderBy('kategori')->orderBy('urutan')->get() as $b) {
            self::$cache[$b->kategori][] = [$b->batas_atas, $b->tarif_persen];
        }
        return self::$cache;
    }

    // Kategori TER berdasarkan status PTKP (sesuai lampiran PMK 168/2023).
    private static function kategori(?string $ptkp): string
    {
        return match ($ptkp) {
            'TK/0', 'TK/1', 'K/0' => 'A',
            'TK/2', 'TK/3', 'K/1', 'K/2' => 'B',
            'K/3' => 'C',
            default => 'A', // fallback kalau PTKP belum diisi
        };
    }

    private static function tarif(float $bruto, string $kategori): float
    {
        $table = self::loadBrackets()[$kategori] ?? [];
        if (empty($table)) return 0;

        foreach ($table as [$batasAtas, $tarif]) {
            if ($batasAtas === null || $bruto <= $batasAtas) {
                return $tarif;
            }
        }
        return end($table)[1];
    }

    // Hitung PPh21 sebulan = penghasilan bruto bulanan x tarif TER sesuai kategori PTKP.
    public static function hitung(float $penghasilanBrutoBulanan, ?string $ptkp): float
    {
        if ($penghasilanBrutoBulanan <= 0) return 0;
        $kategori = self::kategori($ptkp);
        $tarif    = self::tarif($penghasilanBrutoBulanan, $kategori);
        return round($penghasilanBrutoBulanan * $tarif / 100, 2);
    }
}
