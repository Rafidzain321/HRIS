<?php

namespace App\Support;

/**
 * PPh21 bulanan pakai TER (Tarif Efektif Rata-rata) — PP 58/2023 & PMK 168/2023.
 * Di PT. AKM ini sifatnya CATATAN SAJA (metode Netto): pajaknya ditanggung penuh
 * oleh perusahaan, jadi TIDAK dikurangkan dari gaji_bersih karyawan di manapun.
 * Cuma dipakai sebagai info di slip gaji.
 *
 * PENTING: tabel bracket TER di bawah ini diisi berdasarkan pengetahuan umum atas
 * lampiran PMK 168/2023 — karena baris tarifnya sangat banyak & rinci, HR/finance
 * disarankan mengecek ulang angka-angkanya terhadap lampiran resmi sebelum dipakai
 * untuk keperluan pelaporan pajak yang sesungguhnya.
 */
class Pph21Ter
{
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

    // [batas_atas_penghasilan_bruto_bulanan, tarif_persen] — baris terakhir batas_atas = null (tak terhingga).
    private const TER_A = [
        [5400000, 0], [5650000, 0.25], [5950000, 0.5], [6300000, 0.75], [6750000, 1],
        [7500000, 1.25], [8550000, 1.5], [9650000, 1.75], [10050000, 2], [10350000, 2.25],
        [10700000, 2.5], [11050000, 3], [11600000, 3.5], [12500000, 4], [13750000, 5],
        [15100000, 6], [16950000, 7], [19750000, 8], [24150000, 9], [26450000, 10],
        [28000000, 11], [30050000, 12], [32400000, 13], [35400000, 14], [39100000, 15],
        [43850000, 16], [47800000, 17], [51400000, 18], [56300000, 19], [62200000, 20],
        [68600000, 21], [77500000, 22], [89000000, 23], [103000000, 24], [125000000, 25],
        [157000000, 26], [206000000, 27], [337000000, 28], [454000000, 29], [550000000, 30],
        [695000000, 31], [910000000, 32], [1400000000, 33], [null, 34],
    ];

    private const TER_B = [
        [6200000, 0], [6500000, 0.25], [6850000, 0.5], [7300000, 0.75], [9200000, 1],
        [10750000, 1.5], [11250000, 2], [11600000, 2.5], [12600000, 3], [13600000, 4],
        [14950000, 5], [16400000, 6], [18450000, 7], [21850000, 8], [26000000, 9],
        [27700000, 10], [29350000, 11], [31450000, 12], [33950000, 13], [37100000, 14],
        [41100000, 15], [45800000, 16], [49500000, 17], [53800000, 18], [58500000, 19],
        [64000000, 20], [71000000, 21], [80000000, 22], [93000000, 23], [109000000, 24],
        [129000000, 25], [163000000, 26], [211000000, 27], [374000000, 28], [459000000, 29],
        [555000000, 30], [704000000, 31], [957000000, 32], [1405000000, 33], [null, 34],
    ];

    private const TER_C = [
        [6600000, 0], [6950000, 0.25], [7350000, 0.5], [7800000, 0.75], [8850000, 1],
        [10000000, 1.25], [11050000, 1.5], [12000000, 1.75], [12600000, 2], [13600000, 3],
        [14950000, 4], [16400000, 5], [18450000, 6], [21850000, 7], [26000000, 8],
        [27700000, 9], [29350000, 10], [31450000, 11], [33950000, 12], [37100000, 13],
        [41100000, 14], [45800000, 15], [49500000, 16], [53800000, 17], [58500000, 18],
        [64000000, 19], [71000000, 20], [80000000, 21], [93000000, 22], [109000000, 23],
        [129000000, 24], [163000000, 25], [211000000, 26], [374000000, 27], [459000000, 28],
        [555000000, 29], [704000000, 30], [957000000, 31], [1405000000, 32], [null, 34],
    ];

    private static function tarif(float $bruto, string $kategori): float
    {
        $table = match ($kategori) {
            'B' => self::TER_B,
            'C' => self::TER_C,
            default => self::TER_A,
        };
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
