<?php
namespace App\Http\Controllers\Concerns;

use Carbon\Carbon;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

// Helper bersama import Excel (template HRIS: baris 1-4 judul/petunjuk, data mulai baris 5).
trait ImportsExcel
{
    // Sheet ke-$sheetIndex sebagai array berkunci kolom (A, B, ...). null = sheet yang aktif saat file disimpan.
    private function loadSheet(Request $request, ?int $sheetIndex = 0): array
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls|max:10240']);
        $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
        $sheet = $sheetIndex === null ? $spreadsheet->getActiveSheet() : $spreadsheet->getSheet($sheetIndex);
        return $sheet->toArray(null, true, true, true);
    }

    // Baris data (mulai baris 5) beserta nomor baris yang ditampilkan di pesan error.
    // Catatan: nomor yang ditampilkan = nomor baris Excel + 4 (perilaku lama, dipertahankan).
    private function dataRows(array $rows): \Generator
    {
        $rowNum = 4;
        foreach ($rows as $rowIndex => $row) {
            $rowNum++;
            if ($rowIndex < 5) continue;
            yield $rowNum => $row;
        }
    }

    // Sel kosong atau "-", "—", "n/a", "null" (tanpa beda huruf besar/kecil) -> null.
    private static function cleanStr($val): ?string
    {
        $val = trim((string) ($val ?? ''));
        return in_array(strtolower($val), ['-', '—', 'n/a', 'null', '']) ? null : ($val ?: null);
    }

    // Serial tanggal Excel, DD-MM-YYYY, DD/MM/YYYY, atau YYYY-MM-DD -> 'Y-m-d'. Selain itu null.
    private static function parseDate($val): ?string
    {
        if (empty($val) || in_array(trim((string) $val), ['-', '—', 'N/A', 'n/a', ''])) return null;

        if (is_numeric($val)) {
            try {
                return ExcelDate::excelToDateTimeObject($val)->format('Y-m-d');
            } catch (\Exception) {
                return null;
            }
        }

        $val = trim($val);
        foreach (['/^\d{1,2}-\d{1,2}-\d{4}$/' => 'd-m-Y', '/^\d{1,2}\/\d{1,2}\/\d{4}$/' => 'd/m/Y'] as $pattern => $format) {
            if (preg_match($pattern, $val)) {
                try {
                    return Carbon::createFromFormat($format, $val)->format('Y-m-d');
                } catch (\Exception) {
                    return null;
                }
            }
        }
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $val) ? $val : null;
    }

    // Terjemahkan error database jadi pesan yang bisa dipahami user.
    private static function friendlyError(\Exception $e, string $context = '', bool $withForeignKey = true): string
    {
        $msg    = $e->getMessage();
        $prefix = $context ? "$context — " : '';

        if (str_contains($msg, 'cannot be null')) {
            preg_match("/Column '(\w+)' cannot be null/", $msg, $m);
            $col = $m[1] ?? 'kolom wajib';
            $labels = [
                'id_badge'      => 'ID Badge',
                'nama_lengkap'  => 'Nama Lengkap',
                'no_ktp'        => 'No. KTP',
                'operator_name' => 'Nama Operator',
                'no_unit'       => 'No. Unit',
                'employee_id'   => 'Karyawan',
                'name'          => 'Nama',
            ];
            return $prefix . 'Kolom "' . ($labels[$col] ?? $col) . '" wajib diisi tapi kosong.';
        }
        if (str_contains($msg, 'Duplicate entry')) {
            preg_match("/Duplicate entry '(.+?)' for key/", $msg, $m);
            return $prefix . 'Data "' . ($m[1] ?? '') . '" sudah ada di sistem (duplikat).';
        }
        if ($withForeignKey && str_contains($msg, 'foreign key constraint')) {
            return $prefix . 'Data referensi tidak ditemukan di sistem.';
        }
        if (str_contains($msg, 'Data too long')) {
            preg_match("/column '(\w+)'/i", $msg, $m);
            return $prefix . 'Nilai di kolom "' . ($m[1] ?? 'kolom') . '" terlalu panjang.';
        }
        if (str_contains($msg, 'Incorrect date') || str_contains($msg, 'Incorrect datetime')) {
            return $prefix . 'Format tanggal tidak valid. Gunakan format DD-MM-YYYY.';
        }
        return $prefix . 'Gagal disimpan. Periksa kembali data di baris ini.';
    }
}
