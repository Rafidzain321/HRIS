<?php
namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\SioSimOperator;
use App\Models\DriverDetail;
use App\Models\Equipment;
use App\Models\EquipmentOperator;
use App\Models\CcpmManpower;
use App\Models\EmployeeTraining;
use Carbon\Carbon;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class ExportController extends Controller
{
    // ── Warna konstant ──
    const HDR_BG   = 'D9D9D9';
    const HDR_TXT  = '000000';
    const ROW_ODD  = 'FFFFFF';
    const ROW_EVEN = 'F0F2F5';

    // ── Helper: style header ──
    private function headerStyle($sheet, string $cell, string $label): void
    {
        $sheet->setCellValue($cell, $label);
        $sheet->getStyle($cell)->applyFromArray([
            'font'      => ['name'=>'Arial','size'=>9,'bold'=>true,'color'=>['rgb'=>self::HDR_TXT]],
            'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>self::HDR_BG]],
            'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true],
            'borders'   => ['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'AAAAAA']]],
        ]);
    }

    // ── Helper: style cell biasa ──
    private function cellStyle($sheet, string $cell, string $bg, bool $bold = false, string $txt = '1A1A2E'): void
    {
        $sheet->getStyle($cell)->applyFromArray([
            'font'      => ['name'=>'Arial','size'=>9,'color'=>['rgb'=>$txt],'bold'=>$bold],
            'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>$bg]],
            'alignment' => ['horizontal'=>Alignment::HORIZONTAL_LEFT,'vertical'=>Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'D0D3DC']]],
        ]);
    }

    // ── Helper: conditional formatting tanggal ──
    private function addDateConditional($sheet, string $col, int $startRow, int $endRow): void
    {
        $range = "{$col}{$startRow}:{$col}{$endRow}";

        $condExpired = new Conditional();
        $condExpired->setConditionType(Conditional::CONDITION_EXPRESSION);
        $condExpired->addCondition("AND(ISNUMBER({$col}{$startRow}),{$col}{$startRow}<TODAY())");
        $condExpired->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFCDD2');
        $condExpired->getStyle()->getFont()->getColor()->setRGB('B71C1C');
        $condExpired->getStyle()->getFont()->setBold(true);

        $condWarning = new Conditional();
        $condWarning->setConditionType(Conditional::CONDITION_EXPRESSION);
        $condWarning->addCondition("AND(ISNUMBER({$col}{$startRow}),{$col}{$startRow}>=TODAY(),{$col}{$startRow}<TODAY()+30)");
        $condWarning->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF9C4');
        $condWarning->getStyle()->getFont()->getColor()->setRGB('7B5E00');

        $sheet->getStyle($range)->setConditionalStyles([$condExpired, $condWarning]);
    }

    // ── Helper: set date cell ──
    private function setDateCell($sheet, string $col, int $row, $date, string $rowBg): void
    {
        if ($date) {
            $d = $date instanceof Carbon ? $date : Carbon::parse($date);
            $sheet->setCellValue($col.$row, ExcelDate::PHPToExcel($d->timestamp));
            $sheet->getStyle($col.$row)->getNumberFormat()->setFormatCode('DD MMM YYYY');
        } else {
            $sheet->setCellValue($col.$row, '—');
        }
        $this->cellStyle($sheet, $col.$row, $rowBg);
    }

    // ── Helper: buat header sheet ──
    private function makeTitle($sheet, string $title, string $lastCol, int $count): void
    {
        $sheet->mergeCells("A1:{$lastCol}1");
        $projectId = session('active_project_kode');
        $projectLabel = $projectId
            ? strtoupper(\App\Models\Project::find($projectId)?->kode ?? 'UNKNOWN')
            : 'SEMUA PROJECT';
        $sheet->setCellValue('A1', strtoupper($title) . ' — PT. ANDALAS KARYA MULIA (' . $projectLabel . ')');
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['name'=>'Arial','size'=>13,'bold'=>true,'color'=>['rgb'=>'E8A020']],
            'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'1A1A2E']],
            'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->setCellValue('A2', 'Diekspor pada: ' . now()->format('d M Y H:i') . ' WIB  |  Total: ' . $count . ' data');
        $sheet->getStyle('A2')->applyFromArray([
            'font'      => ['name'=>'Arial','size'=>9,'italic'=>true,'color'=>['rgb'=>'666666']],
            'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(16);
        $sheet->getRowDimension(3)->setRowHeight(6);
    }

    // ── Helper: buat legend ──
    private function makeLegend($sheet, int $row): void
    {
        $sheet->mergeCells("A{$row}:B{$row}");
        $sheet->setCellValue("A{$row}", 'Keterangan Warna:');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(9)->setName('Arial');
        $sheet->getRowDimension($row)->setRowHeight(16);

        $row++;
        $sheet->mergeCells("A{$row}:B{$row}");
        $sheet->setCellValue("A{$row}", 'Sudah Expired');
        $sheet->getStyle("A{$row}")->applyFromArray([
            'font'      => ['name'=>'Arial','size'=>9,'bold'=>true,'color'=>['rgb'=>'B71C1C']],
            'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'FFCDD2']],
            'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'CCCCCC']]],
        ]);
        $sheet->mergeCells("C{$row}:E{$row}");
        $sheet->setCellValue("C{$row}", 'Akan Expired < 30 Hari');
        $sheet->getStyle("C{$row}")->applyFromArray([
            'font'      => ['name'=>'Arial','size'=>9,'bold'=>true,'color'=>['rgb'=>'7B5E00']],
            'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'FFF9C4']],
            'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'CCCCCC']]],
        ]);
        $sheet->mergeCells("F{$row}:G{$row}");
        $sheet->setCellValue("F{$row}", 'Masih Valid');
        $sheet->getStyle("F{$row}")->applyFromArray([
            'font'      => ['name'=>'Arial','size'=>9,'bold'=>true,'color'=>['rgb'=>'1B5E20']],
            'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'C8E6C9']],
            'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'CCCCCC']]],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(16);
    }

    // ── Helper: print settings ──
    private function printSettings($sheet, string $freeze, string $filterRange): void
    {
        $sheet->freezePane($freeze);
        $sheet->setAutoFilter($filterRange);
        $sheet->setShowGridlines(false);
        $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A3);
        $sheet->getPageSetup()->setFitToPage(true);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(0.5)->setBottom(0.5)->setLeft(0.4)->setRight(0.4);
        $sheet->getHeaderFooter()->setOddHeader('')->setOddFooter('');
    }

    // ── Helper: stream response ──
    private function streamExcel(Spreadsheet $wb, string $filename)
    {
        $writer = new Xlsx($wb);
        return response()->stream(function() use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control'       => 'max-age=0',
        ]);
    }

    // ── Helper: ambil active project id ──
    private function getProjectId(): ?int
    {
        $user = auth()->user();
        if (!$user) return null;
        if ($user->hasRole('super-admin')) {
            return session('active_project_kode') ?: null;
        }
        if ($user->hasRole('viewer')) return null;
        return $user->project_id;
    }

    // ═══════════════════════════════════════════════════
    // 1. DATA KARYAWAN
    // ═══════════════════════════════════════════════════
    public function karyawan(Request $request)
    {
        $pid = $this->getProjectId();
        $employees = Employee::aktif()->with(['position','ppe','documents'])
            ->when($pid, fn($q) => $q->where('project_id', $pid))
            ->orderBy('nama_lengkap')->get();
        $wb    = new Spreadsheet();
        $sheet = $wb->getActiveSheet()->setTitle('Data Karyawan');
        $sheet = $wb->getActiveSheet();

        $this->makeTitle($sheet, 'Data Karyawan Aktif', 'AH', $employees->count());

        $headers = [
            'A'=>['No.',4],'B'=>['Nama Lengkap',28],'C'=>['NIK / KTP',20],'D'=>['ID Badge',14],
            'E'=>['Jabatan',22],'F'=>['No. Telepon',14],'G'=>['Tempat Lahir',16],
            'H'=>['Tgl Lahir',13],'I'=>['Tgl Masuk',13],'J'=>['Agama',12],'K'=>['Alamat',32],'L'=>['PTKP',8],
            'M'=>['No. Rekening',18],
            'N'=>['Type SIM',9],'O'=>['No. SIM',18],'P'=>['Expired SIM',13],
            'Q'=>['SIO K3',8],'R'=>['No. SIO',18],'S'=>['Expire SIO',13],
            'T'=>['Tgl MCU',13],'U'=>['Expired MCU',13],'V'=>['Status MCU',10],'W'=>['DK',7],'X'=>['Lokasi MCU',15],
            'Y'=>['Expire Badge',13],'Z'=>['Status KP',15],'AA'=>['Exp KP',13],'AB'=>['RFID',13],
            'AC'=>['FRC',7],'AD'=>['Sepatu',8],
            'AE'=>['Start PKWT',13],'AF'=>['End PKWT',13],'AG'=>['No. Kontrak',22],'AH'=>['Bln PKWT',9],
        ];

        $hRow = 4;
        foreach ($headers as $col => [$label, $width]) {
            $this->headerStyle($sheet, $col.$hRow, $label);
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        $sheet->getRowDimension($hRow)->setRowHeight(32);

        $dateCols = ['P','S','T','U','Y','AA','AE','AF'];
        $startRow = 5;

        foreach ($employees as $idx => $emp) {
            $row   = $startRow + $idx;
            $rowBg = ($idx % 2 === 0) ? self::ROW_ODD : self::ROW_EVEN;

            $sheet->setCellValue('A'.$row, $idx+1);
            $this->cellStyle($sheet, 'A'.$row, $rowBg);
            $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $sheet->setCellValue('B'.$row, strtoupper($emp->nama_lengkap));
            $this->cellStyle($sheet, 'B'.$row, $rowBg, true);

            $sheet->setCellValueExplicit('C'.$row, $emp->no_ktp ?? '—', DataType::TYPE_STRING);
            $this->cellStyle($sheet, 'C'.$row, $rowBg);

            $sheet->setCellValue('D'.$row, $emp->id_badge ?? '—');
            $this->cellStyle($sheet, 'D'.$row, $rowBg);

            $textData = [
                'E'=>$emp->position?->nama_jabatan,'F'=>$emp->no_telepon,'G'=>$emp->tempat_lahir,
                'H'=>$emp->tanggal_lahir?->format('d M Y'),'I'=>$emp->tanggal_masuk?->format('d M Y'),
                'J'=>$emp->agama,'K'=>$emp->alamat,'L'=>$emp->ptkp,
                'M'=>$emp->no_rekening,
                'N'=>$emp->type_sim,
                'Q'=>$emp->sio_k3==='YES'?'Ya':($emp->sio_k3==='NO'?'Tidak':'—'),
                'V'=>$emp->status_mcu,'W'=>$emp->derajat_kesehatan,'X'=>$emp->lokasi_mcu,
                'Z'=>$emp->status_kp,'AB'=>$emp->rfid,'AC'=>$emp->ppe?->frc,'AD'=>$emp->ppe?->safety_shoes,
                'AG'=>$emp->no_contract,'AH'=>$emp->bln_pkwt,
            ];

            foreach ($textData as $col => $val) {
                $sheet->setCellValue($col.$row, $val ?? '—');
                $this->cellStyle($sheet, $col.$row, $rowBg);
            }

            $sheet->setCellValueExplicit('M'.$row, $emp->no_rekening ?? '—', DataType::TYPE_STRING);
            $this->cellStyle($sheet, 'M'.$row, $rowBg);

            $sheet->setCellValueExplicit('O'.$row, $emp->no_sim ?? '—', DataType::TYPE_STRING);
            $this->cellStyle($sheet, 'O'.$row, $rowBg);
            $sheet->setCellValueExplicit('R'.$row, $emp->no_sio ?? '—', DataType::TYPE_STRING);
            $this->cellStyle($sheet, 'R'.$row, $rowBg);

            $this->setDateCell($sheet,'P',$row,$emp->expired_sim,$rowBg);
            $this->setDateCell($sheet,'S',$row,$emp->expire_sio,$rowBg);
            $this->setDateCell($sheet,'T',$row,$emp->tgl_mcu,$rowBg);
            $this->setDateCell($sheet,'U',$row,$emp->exp_mcu,$rowBg);
            $this->setDateCell($sheet,'Y',$row,$emp->expire_badge,$rowBg);
            $this->setDateCell($sheet,'AA',$row,$emp->exp_kp,$rowBg);
            $this->setDateCell($sheet,'AE',$row,$emp->start_pkwt,$rowBg);
            $this->setDateCell($sheet,'AF',$row,$emp->end_pkwt,$rowBg);

            $sheet->getRowDimension($row)->setRowHeight(15);
        }

        $lastRow = $startRow + $employees->count() - 1;
        foreach ($dateCols as $col) $this->addDateConditional($sheet, $col, $startRow, $lastRow);

        $this->makeLegend($sheet, $lastRow + 2);
        $this->printSettings($sheet, 'E5', "A{$hRow}:AH{$hRow}");

        return $this->streamExcel($wb, 'DataKaryawan_'.now()->format('Ymd_His').'.xlsx');
    }

    // ═══════════════════════════════════════════════════
    // 2. MCU
    // ═══════════════════════════════════════════════════
    public function mcu()
    {
       $pid = $this->getProjectId();
        $employees = Employee::aktif()->with('position')
            ->when($pid, fn($q) => $q->where('project_id', $pid))
            ->orderBy('exp_mcu')->get();
        $wb    = new Spreadsheet();
        $sheet = $wb->getActiveSheet()->setTitle('MCU');
        $sheet = $wb->getActiveSheet();

        $this->makeTitle($sheet, 'Data MCU Karyawan', 'I', $employees->count());

        $headers = [
            'A'=>['No.',4],'B'=>['ID Badge',14],'C'=>['Nama Lengkap',28],
            'D'=>['Jabatan',22],'E'=>['Tgl Pelaksanaan MCU',16],'F'=>['Expired MCU',14],
            'G'=>['Status MCU',12],'H'=>['Derajat Kesehatan',14],'I'=>['Lokasi MCU',18],
        ];
        $hRow = 4;
        foreach ($headers as $col => [$label, $width]) {
            $this->headerStyle($sheet, $col.$hRow, $label);
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        $sheet->getRowDimension($hRow)->setRowHeight(32);

        $startRow = 5;
        foreach ($employees as $idx => $emp) {
            $row   = $startRow + $idx;
            $rowBg = ($idx % 2 === 0) ? self::ROW_ODD : self::ROW_EVEN;

            $sheet->setCellValue('A'.$row, $idx+1);
            $this->cellStyle($sheet,'A'.$row,$rowBg);
            $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->setCellValue('B'.$row, $emp->id_badge ?? '—'); $this->cellStyle($sheet,'B'.$row,$rowBg);
            $sheet->setCellValue('C'.$row, strtoupper($emp->nama_lengkap)); $this->cellStyle($sheet,'C'.$row,$rowBg,true);
            $sheet->setCellValue('D'.$row, $emp->position?->nama_jabatan ?? '—'); $this->cellStyle($sheet,'D'.$row,$rowBg);
            $this->setDateCell($sheet,'E',$row,$emp->tgl_mcu,$rowBg);
            $this->setDateCell($sheet,'F',$row,$emp->exp_mcu,$rowBg);
            $sheet->setCellValue('G'.$row, $emp->status_mcu ?? '—'); $this->cellStyle($sheet,'G'.$row,$rowBg);
            $sheet->setCellValue('H'.$row, $emp->derajat_kesehatan ?? '—'); $this->cellStyle($sheet,'H'.$row,$rowBg);
            $sheet->setCellValue('I'.$row, $emp->lokasi_mcu ?? '—'); $this->cellStyle($sheet,'I'.$row,$rowBg);
            $sheet->getRowDimension($row)->setRowHeight(15);
        }

        $lastRow = $startRow + $employees->count() - 1;
        $this->addDateConditional($sheet,'F',$startRow,$lastRow);
        $this->makeLegend($sheet, $lastRow+2);
        $this->printSettings($sheet,'D5',"A{$hRow}:I{$hRow}");

        return $this->streamExcel($wb,'MCU_'.now()->format('Ymd_His').'.xlsx');
    }

    // ═══════════════════════════════════════════════════
    // 3. BADGE & KP
    // ═══════════════════════════════════════════════════
    public function badge()
    {
        $pid = $this->getProjectId();
        $employees = Employee::aktif()->with('position')
            ->when($pid, fn($q) => $q->where('project_id', $pid))
            ->orderBy('expire_badge')->get();
        $wb    = new Spreadsheet();
        $sheet = $wb->getActiveSheet()->setTitle('Badge & KP');
        $sheet = $wb->getActiveSheet();

        $this->makeTitle($sheet, 'Data Badge & KP Karyawan', 'I', $employees->count());

        $headers = [
            'A'=>['No.',4],'B'=>['ID Badge',14],'C'=>['Nama Lengkap',28],
            'D'=>['Jabatan',22],'E'=>['RFID',14],'F'=>['Expire Badge',14],
            'G'=>['Status KP',18],'H'=>['Exp KP',14],'I'=>['KP Ready',18],
        ];
        $hRow = 4;
        foreach ($headers as $col => [$label, $width]) {
            $this->headerStyle($sheet, $col.$hRow, $label);
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        $sheet->getRowDimension($hRow)->setRowHeight(32);

        $startRow = 5;
        foreach ($employees as $idx => $emp) {
            $row   = $startRow + $idx;
            $rowBg = ($idx % 2 === 0) ? self::ROW_ODD : self::ROW_EVEN;

            $sheet->setCellValue('A'.$row, $idx+1); $this->cellStyle($sheet,'A'.$row,$rowBg);
            $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->setCellValue('B'.$row, $emp->id_badge ?? '—'); $this->cellStyle($sheet,'B'.$row,$rowBg);
            $sheet->setCellValue('C'.$row, strtoupper($emp->nama_lengkap)); $this->cellStyle($sheet,'C'.$row,$rowBg,true);
            $sheet->setCellValue('D'.$row, $emp->position?->nama_jabatan ?? '—'); $this->cellStyle($sheet,'D'.$row,$rowBg);
            $sheet->setCellValueExplicit('E'.$row, $emp->rfid ?? '—', DataType::TYPE_STRING); $this->cellStyle($sheet,'E'.$row,$rowBg);
            $this->setDateCell($sheet,'F',$row,$emp->expire_badge,$rowBg);
            $sheet->setCellValue('G'.$row, $emp->status_kp ?? '—'); $this->cellStyle($sheet,'G'.$row,$rowBg);
            $this->setDateCell($sheet,'H',$row,$emp->exp_kp,$rowBg);
            $sheet->setCellValue('I'.$row, $emp->kp_ready ?? '—'); $this->cellStyle($sheet,'I'.$row,$rowBg);
            $sheet->getRowDimension($row)->setRowHeight(15);
        }

        $lastRow = $startRow + $employees->count() - 1;
        foreach (['F','H'] as $col) $this->addDateConditional($sheet,$col,$startRow,$lastRow);
        $this->makeLegend($sheet,$lastRow+2);
        $this->printSettings($sheet,'D5',"A{$hRow}:I{$hRow}");

        return $this->streamExcel($wb,'Badge_KP_'.now()->format('Ymd_His').'.xlsx');
    }

    // ═══════════════════════════════════════════════════
    // 4. SIM
    // ═══════════════════════════════════════════════════
    public function sim()
    {
        $pid = $this->getProjectId();
        $employees = Employee::aktif()->with('position')
            ->when($pid, fn($q) => $q->where('project_id', $pid))
            ->whereNotNull('type_sim')->orderBy('expired_sim')->get();
        $wb    = new Spreadsheet();
        $sheet = $wb->getActiveSheet()->setTitle('SIM Karyawan');
        $sheet = $wb->getActiveSheet();

        $this->makeTitle($sheet,'Data SIM Karyawan','H',$employees->count());

        $headers = [
            'A'=>['No.',4],'B'=>['ID Badge',14],'C'=>['Nama Lengkap',28],
            'D'=>['Jabatan',22],'E'=>['Type SIM',10],'F'=>['No. SIM',20],
            'G'=>['Kota Dikeluarkan',18],'H'=>['Expired SIM',14],
        ];
        $hRow = 4;
        foreach ($headers as $col => [$label, $width]) {
            $this->headerStyle($sheet,$col.$hRow,$label);
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        $sheet->getRowDimension($hRow)->setRowHeight(32);

        $startRow = 5;
        foreach ($employees as $idx => $emp) {
            $row   = $startRow + $idx;
            $rowBg = ($idx % 2 === 0) ? self::ROW_ODD : self::ROW_EVEN;

            $sheet->setCellValue('A'.$row,$idx+1); $this->cellStyle($sheet,'A'.$row,$rowBg);
            $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->setCellValue('B'.$row,$emp->id_badge??'—'); $this->cellStyle($sheet,'B'.$row,$rowBg);
            $sheet->setCellValue('C'.$row,strtoupper($emp->nama_lengkap)); $this->cellStyle($sheet,'C'.$row,$rowBg,true);
            $sheet->setCellValue('D'.$row,$emp->position?->nama_jabatan??'—'); $this->cellStyle($sheet,'D'.$row,$rowBg);
            $sheet->setCellValue('E'.$row,$emp->type_sim??'—'); $this->cellStyle($sheet,'E'.$row,$rowBg);
            $sheet->setCellValueExplicit('F'.$row,$emp->no_sim??'—',DataType::TYPE_STRING); $this->cellStyle($sheet,'F'.$row,$rowBg);
            $sheet->setCellValue('G'.$row,$emp->sim_kota_keluar??'—'); $this->cellStyle($sheet,'G'.$row,$rowBg);
            $this->setDateCell($sheet,'H',$row,$emp->expired_sim,$rowBg);
            $sheet->getRowDimension($row)->setRowHeight(15);
        }

        $lastRow = $startRow + $employees->count() - 1;
        $this->addDateConditional($sheet,'H',$startRow,$lastRow);
        $this->makeLegend($sheet,$lastRow+2);
        $this->printSettings($sheet,'D5',"A{$hRow}:H{$hRow}");

        return $this->streamExcel($wb,'SIM_'.now()->format('Ymd_His').'.xlsx');
    }

    // ═══════════════════════════════════════════════════
    // 5. GOI OPERATOR (SioSim)
    // ═══════════════════════════════════════════════════
    public function siosim()
    {
        $operators = SioSimOperator::orderBy('nama')->get();
        $wb    = new Spreadsheet();
        $sheet = $wb->getActiveSheet()->setTitle('GOI Operator');
        $sheet = $wb->getActiveSheet();

        $this->makeTitle($sheet,'Data GOI Operator (SIO/SIM)','G',$operators->count());

        $headers = [
            'A'=>['No.',4],'B'=>['ID Badge',14],'C'=>['Nama',28],
            'D'=>['License Expired',14],'E'=>['KP Expired',14],
            'F'=>['SIO Expired',14],'G'=>['Keterangan',24],
        ];
        $hRow = 4;
        foreach ($headers as $col => [$label,$width]) {
            $this->headerStyle($sheet,$col.$hRow,$label);
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        $sheet->getRowDimension($hRow)->setRowHeight(32);

        $startRow = 5;
        foreach ($operators as $idx => $op) {
            $row   = $startRow + $idx;
            $rowBg = ($idx % 2 === 0) ? self::ROW_ODD : self::ROW_EVEN;

            $sheet->setCellValue('A'.$row,$idx+1); $this->cellStyle($sheet,'A'.$row,$rowBg);
            $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->setCellValue('B'.$row,$op->id_badge??$op->badge??'—'); $this->cellStyle($sheet,'B'.$row,$rowBg);
            $sheet->setCellValue('C'.$row,strtoupper($op->nama)); $this->cellStyle($sheet,'C'.$row,$rowBg,true);
            $this->setDateCell($sheet,'D',$row,$op->license_expired,$rowBg);
            $this->setDateCell($sheet,'E',$row,$op->kp_expired,$rowBg);
            $this->setDateCell($sheet,'F',$row,$op->sio_expired,$rowBg);
            $sheet->setCellValue('G'.$row,$op->keterangan??'—'); $this->cellStyle($sheet,'G'.$row,$rowBg);
            $sheet->getRowDimension($row)->setRowHeight(15);
        }

        $lastRow = $startRow + $operators->count() - 1;
        foreach (['D','E','F'] as $col) $this->addDateConditional($sheet,$col,$startRow,$lastRow);
        $this->makeLegend($sheet,$lastRow+2);
        $this->printSettings($sheet,'C5',"A{$hRow}:G{$hRow}");

        return $this->streamExcel($wb,'GOIOperator_'.now()->format('Ymd_His').'.xlsx');
    }

    // ═══════════════════════════════════════════════════
    // 6. DRIVER
    // ═══════════════════════════════════════════════════
    public function driver()
    {
        $pid = $this->getProjectId();
        $drivers = DriverDetail::when($pid, fn($q) => $q->where('project_id', $pid))
            ->orderBy('name')->get();
        $wb    = new Spreadsheet();
        $sheet = $wb->getActiveSheet()->setTitle('Driver');
        $sheet = $wb->getActiveSheet();

        $this->makeTitle($sheet,'Data Driver','M',$drivers->count());

        $headers = [
            'A'=>['No.',4],'B'=>['Badge',14],'C'=>['Nama Driver',28],'D'=>['NIK',20],
            'E'=>['Type SIM',10],'F'=>['No. SIM',18],'G'=>['RFID',14],
            'H'=>['Jadwal Post Test',14],'I'=>['Permit Expired',14],
            'J'=>['Driver Status',20],'K'=>['Post Test',12],
            'L'=>['Tgl Approve',14],'M'=>['DVP Status',24],
        ];
        $hRow = 4;
        foreach ($headers as $col => [$label,$width]) {
            $this->headerStyle($sheet,$col.$hRow,$label);
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        $sheet->getRowDimension($hRow)->setRowHeight(32);

        $startRow = 5;
        foreach ($drivers as $idx => $d) {
            $row   = $startRow + $idx;
            $rowBg = ($idx % 2 === 0) ? self::ROW_ODD : self::ROW_EVEN;

            $sheet->setCellValue('A'.$row,$idx+1); $this->cellStyle($sheet,'A'.$row,$rowBg);
            $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->setCellValue('B'.$row,$d->badge??'—'); $this->cellStyle($sheet,'B'.$row,$rowBg);
            $sheet->setCellValue('C'.$row,strtoupper($d->name)); $this->cellStyle($sheet,'C'.$row,$rowBg,true);
            $sheet->setCellValueExplicit('D'.$row,$d->id_card??'—',DataType::TYPE_STRING); $this->cellStyle($sheet,'D'.$row,$rowBg);
            $sheet->setCellValue('E'.$row,$d->license_type??'—'); $this->cellStyle($sheet,'E'.$row,$rowBg);
            $sheet->setCellValueExplicit('F'.$row,$d->license_no??'—',DataType::TYPE_STRING); $this->cellStyle($sheet,'F'.$row,$rowBg);
            $sheet->setCellValueExplicit('G'.$row,$d->rfid??'—',DataType::TYPE_STRING); $this->cellStyle($sheet,'G'.$row,$rowBg);
            $this->setDateCell($sheet,'H',$row,$d->posttest_schedule,$rowBg);
            $this->setDateCell($sheet,'I',$row,$d->permit_expired_date,$rowBg);
            $sheet->setCellValue('J'.$row,$d->driver_status??'—'); $this->cellStyle($sheet,'J'.$row,$rowBg);
            $sheet->setCellValue('K'.$row,$d->posttest_status??'—'); $this->cellStyle($sheet,'K'.$row,$rowBg);
            $this->setDateCell($sheet,'L',$row,$d->date_approve_posttest,$rowBg);
            $sheet->setCellValue('M'.$row,$d->dvp_status??'—'); $this->cellStyle($sheet,'M'.$row,$rowBg);
            $sheet->getRowDimension($row)->setRowHeight(15);
        }

        $lastRow = $startRow + $drivers->count() - 1;
        $this->addDateConditional($sheet,'I',$startRow,$lastRow);
        $this->makeLegend($sheet,$lastRow+2);
        $this->printSettings($sheet,'C5',"A{$hRow}:M{$hRow}");

        return $this->streamExcel($wb,'Driver_'.now()->format('Ymd_His').'.xlsx');
    }

    // ═══════════════════════════════════════════════════
    // 7. EQUIPMENT UNIT
    // ═══════════════════════════════════════════════════
    public function equipmentUnit()
    {
        $pid = $this->getProjectId();
        $equipments = Equipment::when($pid, fn($q) => $q->where('project_id', $pid))
            ->orderBy('no_unit')->get();
        $wb    = new Spreadsheet();
        $sheet = $wb->getActiveSheet()->setTitle('Equipment Unit');
        $sheet = $wb->getActiveSheet();

        $this->makeTitle($sheet,'Data Equipment & Vehicle','AC',$equipments->count());

        $headers = [
            'A'=>['No.',4],'B'=>['No. Unit',14],'C'=>['Plat',12],'D'=>['Type',18],
            'E'=>['Model',18],'F'=>['Manufacture',16],'G'=>['Serial No.',18],
            'H'=>['GPS Unit ID',14],'I'=>['Tahun',8],'J'=>['Kategori',14],'K'=>['Kapasitas',12],'L'=>['Status',10],
            'M'=>['STNK Exp',14],'N'=>['Pajak Exp',14],'O'=>['KIR Exp',14],
            'P'=>['Izin Non BM',14],'Q'=>['Vehicle Pass',14],'R'=>['Inspection',14],
            'S'=>['SMBR Pass',14],'T'=>['Green Stiker',14],
            'U'=>['SIO Migas No.',22],'V'=>['SIO Migas Exp',14],'W'=>['SIO Disnaker Exp',14],
            'X'=>['K3 P3A2 No.',22],'Y'=>['K3 P3A2 Exp',14],
            'Z'=>['TPE CEM',18],'AA'=>['Contractor CEM',20],'AB'=>['Lokasi Inspeksi',18],
            'AC'=>['Keterangan',24],
        ];
        $hRow = 4;
        foreach ($headers as $col => [$label,$width]) {
            $this->headerStyle($sheet,$col.$hRow,$label);
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        $sheet->getRowDimension($hRow)->setRowHeight(32);

        $startRow = 5;
        foreach ($equipments as $idx => $eq) {
            $row   = $startRow + $idx;
            $rowBg = ($idx % 2 === 0) ? self::ROW_ODD : self::ROW_EVEN;

            $sheet->setCellValue('A'.$row,$idx+1); $this->cellStyle($sheet,'A'.$row,$rowBg);
            $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->setCellValue('B'.$row,$eq->no_unit); $this->cellStyle($sheet,'B'.$row,$rowBg,true);
            $sheet->setCellValue('C'.$row,$eq->plat_nomor??'—'); $this->cellStyle($sheet,'C'.$row,$rowBg);
            $sheet->setCellValue('D'.$row,$eq->type_unit??'—'); $this->cellStyle($sheet,'D'.$row,$rowBg);
            $sheet->setCellValue('E'.$row,$eq->model??'—'); $this->cellStyle($sheet,'E'.$row,$rowBg);
            $sheet->setCellValue('F'.$row,$eq->manufacture??'—'); $this->cellStyle($sheet,'F'.$row,$rowBg);
            $sheet->setCellValue('G'.$row,$eq->serial_no??'—'); $this->cellStyle($sheet,'G'.$row,$rowBg);
            $sheet->setCellValueExplicit('H'.$row,$eq->gps_unit_id??'—',DataType::TYPE_STRING); $this->cellStyle($sheet,'H'.$row,$rowBg);
            $sheet->setCellValue('I'.$row,$eq->tahun??'—'); $this->cellStyle($sheet,'I'.$row,$rowBg);
            $sheet->setCellValue('J'.$row,$eq->kategori??'—'); $this->cellStyle($sheet,'J'.$row,$rowBg);
            $sheet->setCellValue('K'.$row,$eq->kapasitas??'—'); $this->cellStyle($sheet,'K'.$row,$rowBg);
            $sheet->setCellValue('L'.$row,$eq->status??'—'); $this->cellStyle($sheet,'L'.$row,$rowBg);

            $this->setDateCell($sheet,'M',$row,$eq->stnk_expired,$rowBg);
            $this->setDateCell($sheet,'N',$row,$eq->tax_expired,$rowBg);
            $this->setDateCell($sheet,'O',$row,$eq->kir_expired,$rowBg);
            $this->setDateCell($sheet,'P',$row,$eq->izin_non_bm_expired,$rowBg);
            $this->setDateCell($sheet,'Q',$row,$eq->vehicle_pass_expired,$rowBg);
            $this->setDateCell($sheet,'R',$row,$eq->inspection_date,$rowBg);
            $this->setDateCell($sheet,'S',$row,$eq->smbr_pass_expired,$rowBg);
            $this->setDateCell($sheet,'T',$row,$eq->green_stiker_expired,$rowBg);
            $sheet->setCellValue('U'.$row,$eq->sio_migas_no??'—'); $this->cellStyle($sheet,'U'.$row,$rowBg);
            $this->setDateCell($sheet,'V',$row,$eq->sio_migas_expired,$rowBg);
            $this->setDateCell($sheet,'W',$row,$eq->sio_disnaker_expired,$rowBg);
            $sheet->setCellValue('X'.$row,$eq->k3_p3a2_no??'—'); $this->cellStyle($sheet,'X'.$row,$rowBg);
            $this->setDateCell($sheet,'Y',$row,$eq->k3_p3a2_expired,$rowBg);
            $sheet->setCellValue('Z'.$row,$eq->tpe_cem_inspector??'—'); $this->cellStyle($sheet,'Z'.$row,$rowBg);
            $sheet->setCellValue('AA'.$row,$eq->contractor_cem_inspector??'—'); $this->cellStyle($sheet,'AA'.$row,$rowBg);
            $sheet->setCellValue('AB'.$row,$eq->location_of_inspection??'—'); $this->cellStyle($sheet,'AB'.$row,$rowBg);
            $sheet->setCellValue('AC'.$row,$eq->keterangan??'—'); $this->cellStyle($sheet,'AC'.$row,$rowBg);
            $sheet->getRowDimension($row)->setRowHeight(15);
        }

        $lastRow = $startRow + $equipments->count() - 1;
        foreach (['M','N','O','P','Q','R','S','T','V','W','Y'] as $col)
            $this->addDateConditional($sheet,$col,$startRow,$lastRow);
        $this->makeLegend($sheet,$lastRow+2);
        $this->printSettings($sheet,'B5',"A{$hRow}:AC{$hRow}");

        return $this->streamExcel($wb,'Equipment_Unit_'.now()->format('Ymd_His').'.xlsx');
    }

    // ═══════════════════════════════════════════════════
    // 8. EQUIPMENT OPERATOR
    // ═══════════════════════════════════════════════════
    public function equipmentOperator()
    {
        $pid = $this->getProjectId();
        $operators = EquipmentOperator::with('equipment')
            ->when($pid, fn($q) => $q->where('project_id', $pid))
            ->where('is_active',true)->orderBy('operator_name')->get();
        $wb    = new Spreadsheet();
        $sheet = $wb->getActiveSheet()->setTitle('Equipment Operator');
        $sheet = $wb->getActiveSheet();

        $this->makeTitle($sheet,'Data Equipment Operator','R',$operators->count());

        $headers = [
            'A'=>['No.',4],'B'=>['No. Unit',14],'C'=>['Nama Operator',28],'D'=>['Badge',14],
            'E'=>['RFID',14],'F'=>['License No.',18],'G'=>['License Exp',14],
            'H'=>['KP No.',18],'I'=>['KP Exp',14],
            'J'=>['C-Drive Exp',14],'K'=>['Postest Exp',14],
            'L'=>['Permit No.',18],'M'=>['Permit Exp',14],
            'N'=>['SIO Migas No.',22],'O'=>['SIO Migas Exp',14],
            'P'=>['SIO Disnaker Exp',14],
            'Q'=>['K3 P3A2 No.',22],'R'=>['K3 P3A2 Exp',14],
        ];
        $hRow = 4;
        foreach ($headers as $col => [$label,$width]) {
            $this->headerStyle($sheet,$col.$hRow,$label);
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        $sheet->getRowDimension($hRow)->setRowHeight(32);

        $startRow = 5;
        foreach ($operators as $idx => $op) {
            $row   = $startRow + $idx;
            $rowBg = ($idx % 2 === 0) ? self::ROW_ODD : self::ROW_EVEN;

            $sheet->setCellValue('A'.$row,$idx+1); $this->cellStyle($sheet,'A'.$row,$rowBg);
            $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->setCellValue('B'.$row,$op->equipment?->no_unit??'—'); $this->cellStyle($sheet,'B'.$row,$rowBg);
            $sheet->setCellValue('C'.$row,strtoupper($op->operator_name)); $this->cellStyle($sheet,'C'.$row,$rowBg,true);

            $sheet->setCellValue('D'.$row,$op->badge??'—'); $this->cellStyle($sheet,'D'.$row,$rowBg);
            $sheet->setCellValueExplicit('E'.$row,$op->rfid??'—',DataType::TYPE_STRING); $this->cellStyle($sheet,'E'.$row,$rowBg);
            $sheet->setCellValueExplicit('F'.$row,$op->license_no??'—',DataType::TYPE_STRING); $this->cellStyle($sheet,'F'.$row,$rowBg);
            $this->setDateCell($sheet,'G',$row,$op->license_expired_date,$rowBg);
            $sheet->setCellValue('H'.$row,$op->kp_no??'—'); $this->cellStyle($sheet,'H'.$row,$rowBg);
            $this->setDateCell($sheet,'I',$row,$op->kp_expired_date,$rowBg);
            $this->setDateCell($sheet,'J',$row,$op->cdrive_expired_date,$rowBg);
            $this->setDateCell($sheet,'K',$row,$op->postest_expired_date,$rowBg);
            $sheet->setCellValue('L'.$row,$op->permit_no??'—'); $this->cellStyle($sheet,'L'.$row,$rowBg);
            $this->setDateCell($sheet,'M',$row,$op->permit_expired_date,$rowBg);
            $sheet->setCellValue('N'.$row,$op->sio_migas_no??'—'); $this->cellStyle($sheet,'N'.$row,$rowBg);
            $this->setDateCell($sheet,'O',$row,$op->sio_migas_expired,$rowBg);
            $this->setDateCell($sheet,'P',$row,$op->sio_disnaker_expired,$rowBg);
            $sheet->setCellValue('Q'.$row,$op->k3_p3a2_no??'—'); $this->cellStyle($sheet,'Q'.$row,$rowBg);
            $this->setDateCell($sheet,'R'.$row,$op->k3_p3a2_expired,$rowBg);
            $sheet->getRowDimension($row)->setRowHeight(15);
        }

        $lastRow = $startRow + $operators->count() - 1;
        foreach (['G','I','J','K','M','O','P','R'] as $col)
            $this->addDateConditional($sheet,$col,$startRow,$lastRow);
        $this->makeLegend($sheet,$lastRow+2);
        $this->printSettings($sheet,'C5',"A{$hRow}:R{$hRow}");

        return $this->streamExcel($wb,'Equipment_Operator_'.now()->format('Ymd_His').'.xlsx');
    }

    // ═══════════════════════════════════════════════════
    // 9. CCPM
    // ═══════════════════════════════════════════════════
    public function ccpm()
    {
        $pid = $this->getProjectId();
        $manpower = CcpmManpower::when($pid, fn($q) => $q->where('project_id', $pid))
            ->orderBy('name')->get();
        $wb    = new Spreadsheet();
        $sheet = $wb->getActiveSheet()->setTitle('CCPM Manpower');
        $sheet = $wb->getActiveSheet();

        $this->makeTitle($sheet,'Data Manpower CCPM Facility Engineering','L',$manpower->count());

        $headers = [
            'A'=>['No.',4],'B'=>['Badge',14],'C'=>['NIK',20],'D'=>['HES Passport',24],
            'E'=>['Nama',28],'F'=>['Tempat Lahir',16],'G'=>['Tgl Lahir',14],
            'H'=>['Jabatan',22],'I'=>['Team',18],
            'J'=>['Badge Valid',14],'K'=>['FFD Valid',14],
            'L'=>['Status',22],'M'=>['Status Medical',22],
        ];
        $hRow = 4;
        foreach ($headers as $col => [$label,$width]) {
            $this->headerStyle($sheet,$col.$hRow,$label);
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        $sheet->getRowDimension($hRow)->setRowHeight(32);

        $startRow = 5;
        foreach ($manpower as $idx => $m) {
            $row   = $startRow + $idx;
            $rowBg = ($idx % 2 === 0) ? self::ROW_ODD : self::ROW_EVEN;

            $sheet->setCellValue('A'.$row,$idx+1); $this->cellStyle($sheet,'A'.$row,$rowBg);
            $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->setCellValue('B'.$row,$m->badge??'—'); $this->cellStyle($sheet,'B'.$row,$rowBg);
            $sheet->setCellValueExplicit('C'.$row,$m->id_card??'—',DataType::TYPE_STRING); $this->cellStyle($sheet,'C'.$row,$rowBg);
            $sheet->setCellValue('D'.$row,$m->hes_passport??'—'); $this->cellStyle($sheet,'D'.$row,$rowBg);
            $sheet->setCellValue('E'.$row,strtoupper($m->name)); $this->cellStyle($sheet,'E'.$row,$rowBg,true);
            $sheet->setCellValue('F'.$row,$m->birth_place??'—'); $this->cellStyle($sheet,'F'.$row,$rowBg);
            $this->setDateCell($sheet,'G',$row,$m->birth_date,$rowBg);
            $sheet->setCellValue('H'.$row,$m->job_title??'—'); $this->cellStyle($sheet,'H'.$row,$rowBg);
            $sheet->setCellValue('I'.$row,$m->team_assignment??'—'); $this->cellStyle($sheet,'I'.$row,$rowBg);
            $this->setDateCell($sheet,'J',$row,$m->badge_valid_date,$rowBg);
            $this->setDateCell($sheet,'K',$row,$m->ffd_valid_date,$rowBg);
            $sheet->setCellValue('L'.$row,$m->status??'—'); $this->cellStyle($sheet,'L'.$row,$rowBg);
            $sheet->setCellValue('M'.$row,$m->status_medical??'—'); $this->cellStyle($sheet,'M'.$row,$rowBg);
            $sheet->getRowDimension($row)->setRowHeight(15);
        }

        $lastRow = $startRow + $manpower->count() - 1;
        foreach (['J','K'] as $col) $this->addDateConditional($sheet,$col,$startRow,$lastRow);
        $this->makeLegend($sheet,$lastRow+2);
        $this->printSettings($sheet,'E5',"A{$hRow}:M{$hRow}");

        return $this->streamExcel($wb,'CCPM_'.now()->format('Ymd_His').'.xlsx');
    }

    // ═══════════════════════════════════════════════════
    // 10. TRAINING
    // ═══════════════════════════════════════════════════
    public function training()
    {
        $pid = $this->getProjectId();
        $trainings = EmployeeTraining::with(['employee.position','trainingType'])
            ->when($pid, fn($q) => $q->whereHas('employee', fn($eq) => $eq->where('project_id', $pid)))
            ->orderBy('employee_id')->get();
        $wb    = new Spreadsheet();
        $sheet = $wb->getActiveSheet()->setTitle('Training');
        $sheet = $wb->getActiveSheet();

        $this->makeTitle($sheet,'Data Training Karyawan','K',$trainings->count());

        $headers = [
            'A'=>['No.',4],'B'=>['ID Badge',14],'C'=>['Nama Karyawan',28],
            'D'=>['Jabatan',22],'E'=>['Jenis Training',24],
            'F'=>['Tgl Training',14],'G'=>['Trainer',20],
            'H'=>['Nilai',8],'I'=>['Status',10],
            'J'=>['Expired',14],'K'=>['Ket.',20],
        ];
        $hRow = 4;
        foreach ($headers as $col => [$label,$width]) {
            $this->headerStyle($sheet,$col.$hRow,$label);
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        $sheet->getRowDimension($hRow)->setRowHeight(32);

        $startRow = 5;
        foreach ($trainings as $idx => $t) {
            $row   = $startRow + $idx;
            $rowBg = ($idx % 2 === 0) ? self::ROW_ODD : self::ROW_EVEN;

            $sheet->setCellValue('A'.$row,$idx+1); $this->cellStyle($sheet,'A'.$row,$rowBg);
            $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->setCellValue('B'.$row,$t->employee?->id_badge??'—'); $this->cellStyle($sheet,'B'.$row,$rowBg);
            $sheet->setCellValue('C'.$row,strtoupper($t->employee?->nama_lengkap??'—')); $this->cellStyle($sheet,'C'.$row,$rowBg,true);
            $sheet->setCellValue('D'.$row,$t->employee?->position?->nama_jabatan??'—'); $this->cellStyle($sheet,'D'.$row,$rowBg);
            $sheet->setCellValue('E'.$row,$t->trainingType?->nama??'—'); $this->cellStyle($sheet,'E'.$row,$rowBg);
            $this->setDateCell($sheet,'F',$row,$t->tanggal,$rowBg);
            $sheet->setCellValue('G'.$row,$t->nama_trainer??'—'); $this->cellStyle($sheet,'G'.$row,$rowBg);
            $sheet->setCellValue('H'.$row,$t->nilai??'—'); $this->cellStyle($sheet,'H'.$row,$rowBg);
            $sheet->setCellValue('I'.$row,$t->status??'—'); $this->cellStyle($sheet,'I'.$row,$rowBg);

            // Expired — pakai auto_expired dari model
            $expDate = $t->auto_expired ? Carbon::parse($t->auto_expired) : null;
            $this->setDateCell($sheet,'J',$row,$expDate,$rowBg);

            $sheet->setCellValue('K'.$row,$t->catatan??'—'); $this->cellStyle($sheet,'K'.$row,$rowBg);
            $sheet->getRowDimension($row)->setRowHeight(15);
        }

        $lastRow = $startRow + $trainings->count() - 1;
        $this->addDateConditional($sheet,'J',$startRow,$lastRow);
        $this->makeLegend($sheet,$lastRow+2);
        $this->printSettings($sheet,'C5',"A{$hRow}:K{$hRow}");

        return $this->streamExcel($wb,'Training_'.now()->format('Ymd_His').'.xlsx');
    }
    // ═══════════════════════════════════════════════════
    // PPE EXPORT — format sesuai dokumen AKM
    // ═══════════════════════════════════════════════════
    public function ppe()
    {
        $employees = Employee::aktif()
            ->with(['position','ppe'])
            ->orderBy('nama_lengkap')
            ->get();

        $wb    = new Spreadsheet();
        $sheet = $wb->getActiveSheet()->setTitle('PPE '.now()->year);
        $sheet = $wb->getActiveSheet();
        $sheet->setShowGridlines(false);

        // ── HEADER PERUSAHAAN (baris 1-6) ──
        $sheet->mergeCells('A1:P1');
        $sheet->setCellValue('A1', 'PT. Andalas Karya Mulia');
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['name'=>'Arial','size'=>14,'bold'=>true,'color'=>['rgb'=>'C00000']],
            'alignment' => ['horizontal'=>Alignment::HORIZONTAL_LEFT,'vertical'=>Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(20);

        $sheet->mergeCells('A2:P2');
        $sheet->setCellValue('A2', 'Contractor, Supplier & Heavy Duty Equipment Rental');
        $sheet->getStyle('A2')->getFont()->setName('Arial')->setSize(10)->setItalic(true);
        $sheet->getRowDimension(2)->setRowHeight(14);

        $sheet->mergeCells('A3:P3');
        $sheet->setCellValue('A3', 'Jl. Wonosari, Komplek Wonosari Regency Blok B No.1 Tangkerang Selatan - Pekanbaru');
        $sheet->getStyle('A3')->getFont()->setName('Arial')->setSize(9);
        $sheet->getRowDimension(3)->setRowHeight(13);

        $sheet->mergeCells('A4:P4');
        $sheet->setCellValue('A4', 'Telp. 0761-39213  Fax. 0761-39213  Web : http://www.andalaskarya.com');
        $sheet->getStyle('A4')->getFont()->setName('Arial')->setSize(9);
        $sheet->getRowDimension(4)->setRowHeight(12);

        $sheet->getRowDimension(5)->setRowHeight(6);

        // Judul PPE
        $sheet->mergeCells('D6:P6');
        $sheet->setCellValue('D6', 'P P E '.now()->year);
        $sheet->getStyle('D6')->applyFromArray([
            'font'      => ['name'=>'Arial','size'=>13,'bold'=>true],
            'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(6)->setRowHeight(18);

        // ── HEADER TABEL ──
        $hdrBg  = 'BDD7EE';
        $hdrBg2 = 'D9E1F2';

        // Row 7-10: NO, Name, Job Title — merge 4 baris
        $sheet->mergeCells('A7:A10'); $sheet->setCellValue('A7','NO');        $this->applyPpeHdrStyle($sheet,'A7',$hdrBg);
        $sheet->mergeCells('B7:B10'); $sheet->setCellValue('B7','Name');      $this->applyPpeHdrStyle($sheet,'B7',$hdrBg);
        $sheet->mergeCells('C7:C10'); $sheet->setCellValue('C7','Job Title'); $this->applyPpeHdrStyle($sheet,'C7',$hdrBg);

        // Row 7: UKURAN (D-E), TGL PENGAMBILAN (F-R)
        $sheet->mergeCells('D7:E7'); $sheet->setCellValue('D7','UKURAN');          $this->applyPpeHdrStyle($sheet,'D7',$hdrBg);
        $sheet->mergeCells('F7:P7'); $sheet->setCellValue('F7','TGL PENGAMBILAN'); $this->applyPpeHdrStyle($sheet,'F7',$hdrBg);

        // Row 8: FRC ukuran (D, merge 8-10), SHOES ukuran (E, merge 8-10)
        $sheet->mergeCells('D8:D10'); $sheet->setCellValue('D8','FRC');   $this->applyPpeHdrStyle($sheet,'D8',$hdrBg2);
        $sheet->mergeCells('E8:E10'); $sheet->setCellValue('E8','SHOES'); $this->applyPpeHdrStyle($sheet,'E8',$hdrBg2);

        // Row 8: FRC tgl (F-I), SHOES tgl (J-L), HELMET (M merge 8-10), SAFETY GLASS (N-O), SAFETY VEST (P merge 8-10), EAR PLUG (Q-R)
        $sheet->mergeCells('F8:I8'); $sheet->setCellValue('F8','FRC');          $this->applyPpeHdrStyle($sheet,'F8',$hdrBg2);
        $sheet->mergeCells('J8:L8'); $sheet->setCellValue('J8','SHOES');        $this->applyPpeHdrStyle($sheet,'J8',$hdrBg2);
        $sheet->mergeCells('M8:M10'); $sheet->setCellValue('M8','HELMET');      $this->applyPpeHdrStyle($sheet,'M8',$hdrBg2);
        $sheet->mergeCells('N8:N10'); $sheet->setCellValue('N8','SAFETY GLASS'); $this->applyPpeHdrStyle($sheet,'N8',$hdrBg2);
        $sheet->mergeCells('O8:O10'); $sheet->setCellValue('O8','SAFETY VEST');  $this->applyPpeHdrStyle($sheet,'O8',$hdrBg2);
        $sheet->mergeCells('P8:P10'); $sheet->setCellValue('P8','EAR PLUG');     $this->applyPpeHdrStyle($sheet,'P8',$hdrBg2);

        // Row 9: FRC sub-year
        $sheet->mergeCells('F9:G9'); $sheet->setCellValue('F9','2024');     $this->applyPpeHdrStyle($sheet,'F9',$hdrBg2);
        $sheet->mergeCells('H9:I9'); $sheet->setCellValue('H9','2025/2026'); $this->applyPpeHdrStyle($sheet,'H9',$hdrBg2);

        // Row 9: SHOES per tahun (single cell, merge 9-10)
        $sheet->mergeCells('J9:J10'); $sheet->setCellValue('J9','2024'); $this->applyPpeHdrStyle($sheet,'J9',$hdrBg2);
        $sheet->mergeCells('K9:K10'); $sheet->setCellValue('K9','2025'); $this->applyPpeHdrStyle($sheet,'K9',$hdrBg2);
        $sheet->mergeCells('L9:L10'); $sheet->setCellValue('L9','2026'); $this->applyPpeHdrStyle($sheet,'L9',$hdrBg2);

        // Row 10 — semua cell kosong perlu di-style
        foreach(['M10','N10','O10','P10'] as $c) {
            $sheet->setCellValue($c,'');
            $this->applyPpeHdrStyle($sheet,$c,$hdrBg2);
        }

        $sheet->setCellValue('E9',''); $this->applyPpeHdrStyle($sheet,'E9',$hdrBg2);
        $sheet->setCellValue('E10',''); $this->applyPpeHdrStyle($sheet,'E10',$hdrBg2);

        // Row 10: FRC Ke-1 s.d Ke-4
        $sheet->setCellValue('F10','Ke-1'); $this->applyPpeHdrStyle($sheet,'F10',$hdrBg2);
        $sheet->setCellValue('G10','Ke-2'); $this->applyPpeHdrStyle($sheet,'G10',$hdrBg2);
        $sheet->setCellValue('H10','Ke-3'); $this->applyPpeHdrStyle($sheet,'H10',$hdrBg2);
        $sheet->setCellValue('I10','Ke-4'); $this->applyPpeHdrStyle($sheet,'I10',$hdrBg2);

        $sheet->getStyle('A7:P10')->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['rgb' => '888888'],
                ],
            ],
        ]);

        $sheet->getRowDimension(7)->setRowHeight(14);
        $sheet->getRowDimension(8)->setRowHeight(14);
        $sheet->getRowDimension(9)->setRowHeight(13);
        $sheet->getRowDimension(10)->setRowHeight(13);

        // ── LEBAR KOLOM ──
        $widths = [
            'A'=>5,'B'=>28,'C'=>22,
            'D'=>6,'E'=>6,
            'F'=>11,'G'=>11,'H'=>11,'I'=>11,
            'J'=>11,'K'=>11,'L'=>11,
            'M'=>11,
            'N'=>11,'O'=>11,'P'=>11,
        ];
        foreach ($widths as $col=>$w) $sheet->getColumnDimension($col)->setWidth($w);

        // ── DATA mulai row 11 ──
        $startRow = 11;
        foreach ($employees as $idx => $emp) {
            $row   = $startRow + $idx;
            $rowBg = ($idx % 2 === 0) ? 'FFFFFF' : 'F5F5F5';
            $p     = $emp->ppe;

            // No
            $sheet->setCellValue('A'.$row, $idx+1);
            $this->ppeCellStyle($sheet,'A'.$row,$rowBg);
            $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Nama
            $sheet->setCellValue('B'.$row, strtoupper($emp->nama_lengkap));
            $this->ppeCellStyle($sheet,'B'.$row,$rowBg,true);

            // Jabatan
            $sheet->setCellValue('C'.$row, $emp->position?->nama_jabatan ?? '');
            $this->ppeCellStyle($sheet,'C'.$row,$rowBg);

            // Ukuran FRC & Shoes
            $sheet->setCellValue('D'.$row, $p?->frc ?? '');
            $this->ppeCellStyle($sheet,'D'.$row,$rowBg);
            $sheet->getStyle('D'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $sheet->setCellValue('E'.$row, $p?->safety_shoes ?? '');
            $this->ppeCellStyle($sheet,'E'.$row,$rowBg);
            $sheet->getStyle('E'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // FRC tanggal (F=ke1, G=ke2, H=ke3, I=ke4)
            $this->ppeDateCell($sheet,'F',$row,$p?->tgl_frc,$rowBg);
            $this->ppeDateCell($sheet,'G',$row,$p?->tgl_frc_2,$rowBg);
            $this->ppeDateCell($sheet,'H',$row,$p?->tgl_frc_3,$rowBg);
            $this->ppeDateCell($sheet,'I',$row,$p?->tgl_frc_4,$rowBg);

            // Shoes tanggal (J=2024, K=2025, L=2026)
            $this->ppeDateCell($sheet,'J',$row,$p?->tgl_sepatu,$rowBg);
            $this->ppeDateCell($sheet,'K',$row,$p?->tgl_sepatu_2,$rowBg);
            $this->ppeDateCell($sheet,'L',$row,$p?->tgl_sepatu_3,$rowBg);

            // Helmet
            $this->ppeDateCell($sheet,'M',$row,$p?->tgl_helm,$rowBg);

            // Safety Glass (1 kolom)
            $this->ppeDateCell($sheet,'N',$row,$p?->tgl_glass,$rowBg);

            // Safety Vest
            $this->ppeDateCell($sheet,'O',$row,$p?->tgl_vest,$rowBg);

            // Ear Plug (1 kolom)
            $this->ppeDateCell($sheet,'P',$row,$p?->tgl_ear_plug,$rowBg);

            $sheet->getRowDimension($row)->setRowHeight(14);
        }

        // ── AUTOFILTER & FREEZE ──
        $sheet->setAutoFilter('A10:P10');
        $sheet->freezePane('B11');

        // ── PRINT SETTINGS ──
        $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A3);
        $sheet->getPageSetup()->setFitToPage(true);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(0.5)->setBottom(0.5)->setLeft(0.4)->setRight(0.4);

        $filename = 'PPE_'.now()->format('Ymd_His').'.xlsx';
        $writer   = new Xlsx($wb);
        return response()->stream(function() use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control'       => 'max-age=0',
        ]);
    }

    private function ppeHdr($sheet, string $cell, string $label, string $bg, bool $mergeDown=false, int $rowSpan=1): void
    {
        $sheet->setCellValue($cell, $label);
        $this->applyPpeHdrStyle($sheet, $cell, $bg);
        if ($mergeDown && $rowSpan > 1) {
            preg_match('/([A-Z]+)(\d+)/', $cell, $m);
            $endRow = (int)$m[2] + $rowSpan - 1;
            $mergeRange = $m[1].$m[2].':'.$m[1].$endRow;
            try { $sheet->mergeCells($mergeRange); } catch (\Exception $e) {}
        }
    }

    private function applyPpeHdrStyle($sheet, string $cell, string $bg): void
    {
        $sheet->getStyle($cell)->applyFromArray([
            'font'      => ['name'=>'Arial','size'=>9,'bold'=>true],
            'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>$bg]],
            'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true],
            'borders'   => ['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'888888']]],
        ]);
    }

    private function ppeDateCell($sheet, string $col, int $row, $date, string $bg): void
    {
        if ($date) {
            $d = $date instanceof \Carbon\Carbon ? $date : \Carbon\Carbon::parse($date);
            $sheet->setCellValue($col.$row, \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($d->timestamp));
            $sheet->getStyle($col.$row)->getNumberFormat()->setFormatCode('DD/MM/YYYY');
        } else {
            $sheet->setCellValue($col.$row, '');
        }
        $this->ppeCellStyle($sheet, $col.$row, $bg);
        $sheet->getStyle($col.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    private function ppeCellStyle($sheet, string $cell, string $bg, bool $bold=false): void
    {
        $sheet->getStyle($cell)->applyFromArray([
            'font'      => ['name'=>'Arial','size'=>9,'bold'=>$bold],
            'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>$bg]],
            'alignment' => ['horizontal'=>Alignment::HORIZONTAL_LEFT,'vertical'=>Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'CCCCCC']]],
        ]);
    }
}