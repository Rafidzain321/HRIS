<?php
namespace App\Http\Controllers\Concerns;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Gaya laporan Excel sederhana (judul gelap, header abu-abu, baris belang) untuk export Cuti & KPI.
trait ExcelReport
{
    // Judul di baris 1. $count diisi -> baris 2 berisi "Diekspor pada ... | Total: N data"; null -> baris 2 kosong.
    private function reportTitle($sheet, string $title, string $lastCol, ?int $count = null): void
    {
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A1', $title);
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['name' => 'Arial', 'size' => 12, 'bold' => true, 'color' => ['rgb' => 'E8A020']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1A1A2E']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        if ($count !== null) {
            $sheet->mergeCells("A2:{$lastCol}2");
            $sheet->setCellValue('A2', 'Diekspor pada: ' . now()->format('d M Y H:i') . ' WIB  |  Total: ' . $count . ' data');
            $sheet->getStyle('A2')->applyFromArray([
                'font'      => ['name' => 'Arial', 'size' => 9, 'italic' => true, 'color' => ['rgb' => '666666']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
        }
        $sheet->getRowDimension(2)->setRowHeight($count !== null ? 16 : 6);
        $sheet->getRowDimension(3)->setRowHeight(6);
    }

    // $headers: ['A' => [label, lebar], ...]
    private function reportHeaderRow($sheet, array $headers, int $hRow, int $height): void
    {
        foreach ($headers as $col => [$label, $width]) {
            $sheet->setCellValue($col . $hRow, $label);
            $sheet->getStyle($col . $hRow)->applyFromArray([
                'font'      => ['name' => 'Arial', 'size' => 9, 'bold' => true],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9D9D9']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'AAAAAA']]],
            ]);
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        $sheet->getRowDimension($hRow)->setRowHeight($height);
    }

    private function reportRow($sheet, int $row, int $idx, array $values, array $centerCols = []): void
    {
        $rowBg = $idx % 2 === 0 ? 'FFFFFF' : 'F0F2F5';
        foreach ($values as $col => $val) {
            $sheet->setCellValue($col . $row, $val);
            $sheet->getStyle($col . $row)->applyFromArray([
                'font'      => ['name' => 'Arial', 'size' => 9],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rowBg]],
                'alignment' => ['horizontal' => in_array($col, $centerCols) ? Alignment::HORIZONTAL_CENTER : Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D0D3DC']]],
            ]);
        }
        $sheet->getRowDimension($row)->setRowHeight(15);
    }

    private function streamXlsx(Spreadsheet $wb, string $filename, bool $includeCharts = false)
    {
        $writer = new Xlsx($wb);
        $writer->setIncludeCharts($includeCharts);
        return response()->stream(fn() => $writer->save('php://output'), 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'max-age=0',
        ]);
    }
}
