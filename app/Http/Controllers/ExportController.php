<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExcelReport;
use App\Models\ActivityLog;
use App\Models\CcpmManpower;
use App\Models\ClientProject;
use App\Models\DriverDetail;
use App\Models\Employee;
use App\Models\EmployeeTraining;
use App\Models\Equipment;
use App\Models\EquipmentOperator;
use App\Models\Project;
use App\Models\SioSimOperator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

// Export Excel modul compliance & operasional. Tiap tabel didefinisikan sebagai daftar kolom:
//   'B' => [label, lebar, fn($item) => nilai, tipe]
// tipe: 'text' (default), 'bold', 'string' (angka panjang spt NIK/No. SIM disimpan sbg teks),
//       'date', 'expiry' (tanggal + warna merah/kuning kalau expired / < 30 hari).
// Kolom A (No.) selalu otomatis.
class ExportController extends Controller
{
    use ExcelReport;

    const HDR_BG   = 'D9D9D9';
    const HDR_TXT  = '000000';
    const ROW_ODD  = 'FFFFFF';
    const ROW_EVEN = 'F0F2F5';
    const HEADER_ROW = 4;
    const START_ROW  = 5;

    // ═══════════════════════════════════════════════════
    // DATA KARYAWAN
    // ═══════════════════════════════════════════════════
    public function karyawan(Request $request)
    {
        $clientProjectId = $request->get('client_project', '');
        ActivityLog::record('export', 'Data Karyawan', null, 'Export Excel data karyawan aktif' .
            ($clientProjectId ? ' (filter Data Project: ' . (ClientProject::find($clientProjectId)?->kode ?? '-') . ')' : ''));

        $pid = $this->activeProjectId();
        if ($pid && Project::find($pid)?->tipe_gaji === 'ho') {
            return $this->karyawanHo($pid, $clientProjectId);
        }

        $search  = $request->get('search', '');
        $jabatan = $request->get('jabatan', '');
        $employees = Employee::aktif()->with(['position', 'ppe', 'clientProjects'])
            ->when($pid, fn($q) => $q->where('project_id', $pid))
            ->when($search, fn($q) => $q->where(fn($q2) => $q2
                ->where('nama_lengkap', 'like', "%$search%")
                ->orWhere('no_ktp', 'like', "%$search%")
                ->orWhere('id_badge', 'like', "%$search%")
                ->orWhereHas('position', fn($q3) => $q3->where('nama_jabatan', 'like', "%$search%"))
            ))
            ->when($jabatan, fn($q) => $q->whereHas('position', fn($q2) => $q2->where('nama_jabatan', $jabatan)))
            ->when($clientProjectId, fn($q) => $q->whereHas('clientProjects', fn($q2) => $q2->where('client_projects.id', $clientProjectId)))
            ->orderBy('nama_lengkap')->get();

        return $this->exportTable('Data Karyawan', 'Data Karyawan Aktif', $employees, [
            'B'  => ['Nama Lengkap', 28, fn($e) => strtoupper($e->nama_lengkap), 'bold'],
            'C'  => ['NIK / KTP', 20, fn($e) => $e->no_ktp, 'string'],
            'D'  => ['ID Badge', 14, fn($e) => $e->id_badge],
            'E'  => ['Jabatan', 22, fn($e) => $e->position?->nama_jabatan],
            'F'  => ['No. Telepon', 14, fn($e) => $e->no_telepon],
            'G'  => ['Tempat Lahir', 16, fn($e) => $e->tempat_lahir],
            'H'  => ['Tgl Lahir', 13, fn($e) => $e->tanggal_lahir?->format('d M Y')],
            'I'  => ['Tgl Masuk', 13, fn($e) => $e->tanggal_masuk?->format('d M Y')],
            'J'  => ['Agama', 12, fn($e) => $e->agama],
            'K'  => ['Alamat', 32, fn($e) => $e->alamat],
            'L'  => ['PTKP', 8, fn($e) => $e->ptkp],
            'M'  => ['No. Rekening', 18, fn($e) => $e->no_rekening, 'string'],
            'N'  => ['Type SIM', 9, fn($e) => $e->type_sim],
            'O'  => ['No. SIM', 18, fn($e) => $e->no_sim, 'string'],
            'P'  => ['Expired SIM', 13, fn($e) => $e->expired_sim, 'expiry'],
            'Q'  => ['SIO K3', 8, fn($e) => $e->sio_k3 === 'YES' ? 'Ya' : ($e->sio_k3 === 'NO' ? 'Tidak' : '—')],
            'R'  => ['No. SIO', 18, fn($e) => $e->no_sio, 'string'],
            'S'  => ['Expire SIO', 13, fn($e) => $e->expire_sio, 'expiry'],
            'T'  => ['Tgl MCU', 13, fn($e) => $e->tgl_mcu, 'expiry'],
            'U'  => ['Expired MCU', 13, fn($e) => $e->exp_mcu, 'expiry'],
            'V'  => ['Status MCU', 10, fn($e) => $e->status_mcu],
            'W'  => ['DK', 7, fn($e) => $e->derajat_kesehatan],
            'X'  => ['Lokasi MCU', 15, fn($e) => $e->lokasi_mcu],
            'Y'  => ['Expire Badge', 13, fn($e) => $e->expire_badge, 'expiry'],
            'Z'  => ['Status KP', 15, fn($e) => $e->status_kp],
            'AA' => ['Exp KP', 13, fn($e) => $e->exp_kp, 'expiry'],
            'AB' => ['RFID', 13, fn($e) => $e->rfid],
            'AC' => ['FRC', 7, fn($e) => $e->ppe?->frc],
            'AD' => ['Sepatu', 8, fn($e) => $e->ppe?->safety_shoes],
            'AE' => ['Start PKWT', 13, fn($e) => $e->start_pkwt, 'expiry'],
            'AF' => ['End PKWT', 13, fn($e) => $e->end_pkwt, 'expiry'],
            'AG' => ['No. Kontrak', 22, fn($e) => $e->no_contract],
            'AH' => ['Bln PKWT', 9, fn($e) => $e->bln_pkwt],
            'AI' => ['Data Project', 24, fn($e) => $e->clientProjects->pluck('kode')->implode(', ') ?: null],
        ], 'E5', 'DataKaryawan');
    }

    // Kolom HO beda dari kantor lapangan: tanpa SIM/SIO/MCU/Badge/PPE (tidak relevan untuk staf kantor pusat),
    // diganti data EmployeeHoDetail. Baris dikelompokkan per unit: HO-1, HO-2, lalu yang belum ada unit.
    private function karyawanHo(int $pid, $clientProjectId)
    {
        $employees = Employee::aktif()->with(['position', 'hoDetail', 'clientProjects'])
            ->where('project_id', $pid)
            ->when($clientProjectId, fn($q) => $q->whereHas('clientProjects', fn($q2) => $q2->where('client_projects.id', $clientProjectId)))
            ->orderBy('nama_lengkap')->get();

        $unitOrder = ['HO-1' => 1, 'HO-2' => 2];
        $groups = $employees->groupBy(fn($e) => $e->hoDetail?->unit ?: 'Belum Ada Unit')
            ->sortBy(fn($g, $unit) => $unitOrder[$unit] ?? 99);

        $columns = [
            'B'  => ['Nama Lengkap', 28, fn($e) => strtoupper($e->nama_lengkap), 'bold'],
            'C'  => ['NIK / KTP', 20, fn($e) => $e->no_ktp, 'string'],
            'D'  => ['Unit', 7, fn($e) => $e->hoDetail?->unit],
            'E'  => ['NIK HO', 16, fn($e) => $e->hoDetail?->nik_ho],
            'F'  => ['Jabatan', 22, fn($e) => $e->position?->nama_jabatan],
            'G'  => ['No. Telepon', 14, fn($e) => $e->no_telepon],
            'H'  => ['Tempat Lahir', 16, fn($e) => $e->tempat_lahir],
            'I'  => ['Tgl Lahir', 13, fn($e) => $e->tanggal_lahir?->format('d M Y')],
            'J'  => ['Tgl Masuk', 13, fn($e) => $e->tanggal_masuk?->format('d M Y')],
            'K'  => ['Agama', 12, fn($e) => $e->agama],
            'L'  => ['Alamat', 32, fn($e) => $e->alamat],
            'M'  => ['PTKP', 8, fn($e) => $e->ptkp],
            'N'  => ['No. Rekening', 18, fn($e) => $e->no_rekening, 'string'],
            'O'  => ['Status Karyawan', 16, fn($e) => $e->hoDetail?->status_karyawan],
            'P'  => ['Nama (KTP)', 26, fn($e) => $e->hoDetail?->nama_ktp],
            'Q'  => ['No. KK', 20, fn($e) => $e->hoDetail?->no_kk, 'string'],
            'R'  => ['RT/RW', 10, fn($e) => $e->hoDetail?->rt_rw],
            'S'  => ['Kelurahan', 18, fn($e) => $e->hoDetail?->kelurahan],
            'T'  => ['Kecamatan', 18, fn($e) => $e->hoDetail?->kecamatan],
            'U'  => ['Propinsi', 18, fn($e) => $e->hoDetail?->propinsi],
            'V'  => ['NPWP', 20, fn($e) => $e->hoDetail?->npwp, 'string'],
            'W'  => ['Email', 24, fn($e) => $e->hoDetail?->email],
            'X'  => ['Lokasi Kerja', 20, fn($e) => $e->hoDetail?->lokasi_kerja],
            'Y'  => ['Start PKWT', 13, fn($e) => $e->start_pkwt, 'expiry'],
            'Z'  => ['End PKWT', 13, fn($e) => $e->end_pkwt, 'expiry'],
            'AA' => ['No. Kontrak', 22, fn($e) => $e->no_contract],
            'AB' => ['Data Project', 24, fn($e) => $e->clientProjects->pluck('kode')->implode(', ') ?: null],
        ];
        [$wb, $sheet] = $this->newTableSheet('Data Karyawan HO', 'Data Karyawan HO Aktif', $columns, $employees->count());

        $row = self::START_ROW;
        $no  = 0;
        foreach ($groups as $unit => $group) {
            $sheet->mergeCells("A{$row}:AB{$row}");
            $sheet->setCellValue("A{$row}", "{$unit} ({$group->count()} karyawan)");
            $sheet->getStyle("A{$row}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 11],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F4A010']],
            ]);
            $sheet->getRowDimension($row)->setRowHeight(20);
            $row++;

            foreach ($group as $emp) {
                $this->writeRow($sheet, $row++, $no + 1, $emp, $columns, $this->rowBg($no));
                $no++;
            }
        }

        $this->finishTableSheet($sheet, $columns, $row - 1, 'E5');
        return $this->streamXlsx($wb, 'DataKaryawanHO_' . now()->format('Ymd_His') . '.xlsx');
    }

    // ═══════════════════════════════════════════════════
    // COMPLIANCE & OPERASIONAL
    // ═══════════════════════════════════════════════════
    public function mcu()
    {
        ActivityLog::record('export', 'MCU', null, 'Export Excel data MCU');
        $employees = $this->karyawanAktif()->orderBy('exp_mcu')->get();

        return $this->exportTable('MCU', 'Data MCU Karyawan', $employees, [
            'B' => ['ID Badge', 14, fn($e) => $e->id_badge],
            'C' => ['Nama Lengkap', 28, fn($e) => strtoupper($e->nama_lengkap), 'bold'],
            'D' => ['Jabatan', 22, fn($e) => $e->position?->nama_jabatan],
            'E' => ['Tgl Pelaksanaan MCU', 16, fn($e) => $e->tgl_mcu, 'date'],
            'F' => ['Expired MCU', 14, fn($e) => $e->exp_mcu, 'expiry'],
            'G' => ['Status MCU', 12, fn($e) => $e->status_mcu],
            'H' => ['Derajat Kesehatan', 14, fn($e) => $e->derajat_kesehatan],
            'I' => ['Lokasi MCU', 18, fn($e) => $e->lokasi_mcu],
        ], 'D5', 'MCU');
    }

    public function badge()
    {
        ActivityLog::record('export', 'Badge & KP', null, 'Export Excel data Badge');
        $employees = $this->karyawanAktif()->orderBy('expire_badge')->get();

        return $this->exportTable('Badge & KP', 'Data Badge & KP Karyawan', $employees, [
            'B' => ['ID Badge', 14, fn($e) => $e->id_badge],
            'C' => ['Nama Lengkap', 28, fn($e) => strtoupper($e->nama_lengkap), 'bold'],
            'D' => ['Jabatan', 22, fn($e) => $e->position?->nama_jabatan],
            'E' => ['RFID', 14, fn($e) => $e->rfid, 'string'],
            'F' => ['Expire Badge', 14, fn($e) => $e->expire_badge, 'expiry'],
            'G' => ['Status KP', 18, fn($e) => $e->status_kp],
            'H' => ['Exp KP', 14, fn($e) => $e->exp_kp, 'expiry'],
            'I' => ['KP Ready', 18, fn($e) => $e->kp_ready],
        ], 'D5', 'Badge_KP');
    }

    public function sim()
    {
        ActivityLog::record('export', 'SIM', null, 'Export Excel data SIM');
        $employees = $this->karyawanAktif()->whereNotNull('type_sim')->orderBy('expired_sim')->get();

        return $this->exportTable('SIM Karyawan', 'Data SIM Karyawan', $employees, [
            'B' => ['ID Badge', 14, fn($e) => $e->id_badge],
            'C' => ['Nama Lengkap', 28, fn($e) => strtoupper($e->nama_lengkap), 'bold'],
            'D' => ['Jabatan', 22, fn($e) => $e->position?->nama_jabatan],
            'E' => ['Type SIM', 10, fn($e) => $e->type_sim],
            'F' => ['No. SIM', 20, fn($e) => $e->no_sim, 'string'],
            'G' => ['Kota Dikeluarkan', 18, fn($e) => $e->sim_kota_keluar],
            'H' => ['Expired SIM', 14, fn($e) => $e->expired_sim, 'expiry'],
        ], 'D5', 'SIM');
    }

    public function siosim()
    {
        ActivityLog::record('export', 'GOI Operator', null, 'Export Excel data GOI Operator (SIO/SIM)');

        return $this->exportTable('GOI Operator', 'Data GOI Operator (SIO/SIM)', SioSimOperator::orderBy('nama')->get(), [
            'B' => ['ID Badge', 14, fn($o) => $o->id_badge ?? $o->badge],
            'C' => ['Nama', 28, fn($o) => strtoupper($o->nama), 'bold'],
            'D' => ['License Expired', 14, fn($o) => $o->license_expired, 'expiry'],
            'E' => ['KP Expired', 14, fn($o) => $o->kp_expired, 'expiry'],
            'F' => ['SIO Expired', 14, fn($o) => $o->sio_expired, 'expiry'],
            'G' => ['Keterangan', 24, fn($o) => $o->keterangan],
        ], 'C5', 'GOIOperator');
    }

    public function driver()
    {
        ActivityLog::record('export', 'Driver', null, 'Export Excel data Driver');
        $pid = $this->activeProjectId();
        $drivers = DriverDetail::when($pid, fn($q) => $q->where('project_id', $pid))->orderBy('name')->get();

        return $this->exportTable('Driver', 'Data Driver', $drivers, [
            'B' => ['Badge', 14, fn($d) => $d->badge],
            'C' => ['Nama Driver', 28, fn($d) => strtoupper($d->name), 'bold'],
            'D' => ['NIK', 20, fn($d) => $d->id_card, 'string'],
            'E' => ['Type SIM', 10, fn($d) => $d->license_type],
            'F' => ['No. SIM', 18, fn($d) => $d->license_no, 'string'],
            'G' => ['RFID', 14, fn($d) => $d->rfid, 'string'],
            'H' => ['Jadwal Post Test', 14, fn($d) => $d->posttest_schedule, 'date'],
            'I' => ['Permit Expired', 14, fn($d) => $d->permit_expired_date, 'expiry'],
            'J' => ['Driver Status', 20, fn($d) => $d->driver_status],
            'K' => ['Post Test', 12, fn($d) => $d->posttest_status],
            'L' => ['Tgl Approve', 14, fn($d) => $d->date_approve_posttest, 'date'],
            'M' => ['DVP Status', 24, fn($d) => $d->dvp_status],
        ], 'C5', 'Driver');
    }

    public function equipmentUnit()
    {
        ActivityLog::record('export', 'Equipment', null, 'Export Excel data Equipment Unit');
        $pid = $this->activeProjectId();
        $equipments = Equipment::when($pid, fn($q) => $q->where('project_id', $pid))->orderBy('no_unit')->get();

        return $this->exportTable('Equipment Unit', 'Data Equipment & Vehicle', $equipments, [
            'B'  => ['No. Unit', 14, fn($e) => $e->no_unit, 'bold'],
            'C'  => ['Plat', 12, fn($e) => $e->plat_nomor],
            'D'  => ['Type', 18, fn($e) => $e->type_unit],
            'E'  => ['Model', 18, fn($e) => $e->model],
            'F'  => ['Manufacture', 16, fn($e) => $e->manufacture],
            'G'  => ['Serial No.', 18, fn($e) => $e->serial_no],
            'H'  => ['GPS Unit ID', 14, fn($e) => $e->gps_unit_id, 'string'],
            'I'  => ['Tahun', 8, fn($e) => $e->tahun],
            'J'  => ['Kategori', 14, fn($e) => $e->kategori],
            'K'  => ['Kapasitas', 12, fn($e) => $e->kapasitas],
            'L'  => ['Status', 10, fn($e) => $e->status],
            'M'  => ['STNK Exp', 14, fn($e) => $e->stnk_expired, 'expiry'],
            'N'  => ['Pajak Exp', 14, fn($e) => $e->tax_expired, 'expiry'],
            'O'  => ['KIR Exp', 14, fn($e) => $e->kir_expired, 'expiry'],
            'P'  => ['Izin Non BM', 14, fn($e) => $e->izin_non_bm_expired, 'expiry'],
            'Q'  => ['Vehicle Pass', 14, fn($e) => $e->vehicle_pass_expired, 'expiry'],
            'R'  => ['Inspection', 14, fn($e) => $e->inspection_date, 'expiry'],
            'S'  => ['SMBR Pass', 14, fn($e) => $e->smbr_pass_expired, 'expiry'],
            'T'  => ['Green Stiker', 14, fn($e) => $e->green_stiker_expired, 'expiry'],
            'U'  => ['SIO Migas No.', 22, fn($e) => $e->sio_migas_no],
            'V'  => ['SIO Migas Exp', 14, fn($e) => $e->sio_migas_expired, 'expiry'],
            'W'  => ['SIO Disnaker Exp', 14, fn($e) => $e->sio_disnaker_expired, 'expiry'],
            'X'  => ['K3 P3A2 No.', 22, fn($e) => $e->k3_p3a2_no],
            'Y'  => ['K3 P3A2 Exp', 14, fn($e) => $e->k3_p3a2_expired, 'expiry'],
            'Z'  => ['TPE CEM', 18, fn($e) => $e->tpe_cem_inspector],
            'AA' => ['Contractor CEM', 20, fn($e) => $e->contractor_cem_inspector],
            'AB' => ['Lokasi Inspeksi', 18, fn($e) => $e->location_of_inspection],
            'AC' => ['Keterangan', 24, fn($e) => $e->keterangan],
        ], 'B5', 'Equipment_Unit');
    }

    public function equipmentOperator()
    {
        ActivityLog::record('export', 'Equipment', null, 'Export Excel data Equipment Operator');
        $pid = $this->activeProjectId();
        $operators = EquipmentOperator::with('equipment')
            ->when($pid, fn($q) => $q->where('project_id', $pid))
            ->where('is_active', true)->orderBy('operator_name')->get();

        return $this->exportTable('Equipment Operator', 'Data Equipment Operator', $operators, [
            'B' => ['No. Unit', 14, fn($o) => $o->equipment?->no_unit],
            'C' => ['Nama Operator', 28, fn($o) => strtoupper($o->operator_name), 'bold'],
            'D' => ['Badge', 14, fn($o) => $o->badge],
            'E' => ['RFID', 14, fn($o) => $o->rfid, 'string'],
            'F' => ['License No.', 18, fn($o) => $o->license_no, 'string'],
            'G' => ['License Exp', 14, fn($o) => $o->license_expired_date, 'expiry'],
            'H' => ['KP No.', 18, fn($o) => $o->kp_no],
            'I' => ['KP Exp', 14, fn($o) => $o->kp_expired_date, 'expiry'],
            'J' => ['C-Drive Exp', 14, fn($o) => $o->cdrive_expired_date, 'expiry'],
            'K' => ['Postest Exp', 14, fn($o) => $o->postest_expired_date, 'expiry'],
            'L' => ['Permit No.', 18, fn($o) => $o->permit_no],
            'M' => ['Permit Exp', 14, fn($o) => $o->permit_expired_date, 'expiry'],
            'N' => ['SIO Migas No.', 22, fn($o) => $o->sio_migas_no],
            'O' => ['SIO Migas Exp', 14, fn($o) => $o->sio_migas_expired, 'expiry'],
            'P' => ['SIO Disnaker Exp', 14, fn($o) => $o->sio_disnaker_expired, 'expiry'],
            'Q' => ['K3 P3A2 No.', 22, fn($o) => $o->k3_p3a2_no],
            'R' => ['K3 P3A2 Exp', 14, fn($o) => $o->k3_p3a2_expired, 'expiry'],
        ], 'C5', 'Equipment_Operator');
    }

    public function ccpm()
    {
        ActivityLog::record('export', 'CCPM', null, 'Export Excel data CCPM Manpower');
        $pid = $this->activeProjectId();
        $manpower = CcpmManpower::when($pid, fn($q) => $q->where('project_id', $pid))->orderBy('name')->get();

        return $this->exportTable('CCPM Manpower', 'Data Manpower CCPM Facility Engineering', $manpower, [
            'B' => ['Badge', 14, fn($m) => $m->badge],
            'C' => ['NIK', 20, fn($m) => $m->id_card, 'string'],
            'D' => ['HES Passport', 24, fn($m) => $m->hes_passport],
            'E' => ['Nama', 28, fn($m) => strtoupper($m->name), 'bold'],
            'F' => ['Tempat Lahir', 16, fn($m) => $m->birth_place],
            'G' => ['Tgl Lahir', 14, fn($m) => $m->birth_date, 'date'],
            'H' => ['Jabatan', 22, fn($m) => $m->job_title],
            'I' => ['Team', 18, fn($m) => $m->team_assignment],
            'J' => ['Badge Valid', 14, fn($m) => $m->badge_valid_date, 'expiry'],
            'K' => ['FFD Valid', 14, fn($m) => $m->ffd_valid_date, 'expiry'],
            'L' => ['Status', 22, fn($m) => $m->status],
            'M' => ['Status Medical', 22, fn($m) => $m->status_medical],
        ], 'E5', 'CCPM');
    }

    public function training()
    {
        ActivityLog::record('export', 'Training', null, 'Export Excel data Training');
        $pid = $this->activeProjectId();
        $trainings = EmployeeTraining::with(['employee.position', 'trainingType'])
            ->when($pid, fn($q) => $q->whereHas('employee', fn($eq) => $eq->where('project_id', $pid)))
            ->orderBy('employee_id')->get();

        return $this->exportTable('Training', 'Data Training Karyawan', $trainings, [
            'B' => ['ID Badge', 14, fn($t) => $t->employee?->id_badge],
            'C' => ['Nama Karyawan', 28, fn($t) => strtoupper($t->employee?->nama_lengkap ?? '—'), 'bold'],
            'D' => ['Jabatan', 22, fn($t) => $t->employee?->position?->nama_jabatan],
            'E' => ['Jenis Training', 24, fn($t) => $t->trainingType?->nama],
            'F' => ['Tgl Training', 14, fn($t) => $t->tanggal, 'date'],
            'G' => ['Trainer', 20, fn($t) => $t->nama_trainer],
            'H' => ['Nilai', 8, fn($t) => $t->nilai],
            'I' => ['Status', 10, fn($t) => $t->status],
            // Masa berlaku dihitung model (auto_expired) dari tanggal training + masa berlaku jenis training.
            'J' => ['Expired', 14, fn($t) => $t->auto_expired, 'expiry'],
            'K' => ['Ket.', 20, fn($t) => $t->catatan],
        ], 'C5', 'Training');
    }

    // ═══════════════════════════════════════════════════
    // PPE — format mengikuti dokumen PPE AKM (header perusahaan + header tabel bertingkat)
    // ═══════════════════════════════════════════════════
    public function ppe()
    {
        ActivityLog::record('export', 'PPE', null, 'Export Excel data PPE');
        $employees = Employee::aktif()->with(['position', 'ppe'])->orderBy('nama_lengkap')->get();

        $wb    = new Spreadsheet();
        $sheet = $wb->getActiveSheet()->setTitle('PPE ' . now()->year);

        $sheet->mergeCells('A1:P1');
        $sheet->setCellValue('A1', 'PT. Andalas Karya Mulia');
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['name' => 'Arial', 'size' => 14, 'bold' => true, 'color' => ['rgb' => 'C00000']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(20);

        $kop = [
            2 => ['Contractor, Supplier & Heavy Duty Equipment Rental', 10, true, 14],
            3 => ['Jl. Wonosari, Komplek Wonosari Regency Blok B No.1 Tangkerang Selatan - Pekanbaru', 9, false, 13],
            4 => ['Telp. 0761-39213  Fax. 0761-39213  Web : http://www.andalaskarya.com', 9, false, 12],
        ];
        foreach ($kop as $r => [$text, $size, $italic, $height]) {
            $sheet->mergeCells("A{$r}:P{$r}");
            $sheet->setCellValue("A{$r}", $text);
            $sheet->getStyle("A{$r}")->getFont()->setName('Arial')->setSize($size)->setItalic($italic);
            $sheet->getRowDimension($r)->setRowHeight($height);
        }
        $sheet->getRowDimension(5)->setRowHeight(6);

        $sheet->mergeCells('D6:P6');
        $sheet->setCellValue('D6', 'P P E ' . now()->year);
        $sheet->getStyle('D6')->applyFromArray([
            'font'      => ['name' => 'Arial', 'size' => 13, 'bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(6)->setRowHeight(18);

        // Header bertingkat baris 7-10: [range, label, warna].
        $bg1 = 'BDD7EE';
        $bg2 = 'D9E1F2';
        $header = [
            ['A7:A10', 'NO', $bg1], ['B7:B10', 'Name', $bg1], ['C7:C10', 'Job Title', $bg1],
            ['D7:E7', 'UKURAN', $bg1], ['F7:P7', 'TGL PENGAMBILAN', $bg1],
            ['D8:D10', 'FRC', $bg2], ['E8:E10', 'SHOES', $bg2],
            ['F8:I8', 'FRC', $bg2], ['J8:L8', 'SHOES', $bg2],
            ['M8:M10', 'HELMET', $bg2], ['N8:N10', 'SAFETY GLASS', $bg2], ['O8:O10', 'SAFETY VEST', $bg2], ['P8:P10', 'EAR PLUG', $bg2],
            ['F9:G9', '2024', $bg2], ['H9:I9', '2025/2026', $bg2],
            ['J9:J10', '2024', $bg2], ['K9:K10', '2025', $bg2], ['L9:L10', '2026', $bg2],
            ['M10', '', $bg2], ['N10', '', $bg2], ['O10', '', $bg2], ['P10', '', $bg2], ['E9', '', $bg2], ['E10', '', $bg2],
            ['F10', 'Ke-1', $bg2], ['G10', 'Ke-2', $bg2], ['H10', 'Ke-3', $bg2], ['I10', 'Ke-4', $bg2],
        ];
        foreach ($header as [$range, $label, $bg]) {
            if (str_contains($range, ':')) $sheet->mergeCells($range);
            $cell = explode(':', $range)[0];
            $sheet->setCellValue($cell, $label);
            $this->applyPpeHdrStyle($sheet, $cell, $bg);
        }
        $sheet->getStyle('A7:P10')->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '888888']]],
        ]);
        foreach ([7 => 14, 8 => 14, 9 => 13, 10 => 13] as $r => $h) $sheet->getRowDimension($r)->setRowHeight($h);

        $widths = ['A' => 5, 'B' => 28, 'C' => 22, 'D' => 6, 'E' => 6, 'F' => 11, 'G' => 11, 'H' => 11, 'I' => 11,
                   'J' => 11, 'K' => 11, 'L' => 11, 'M' => 11, 'N' => 11, 'O' => 11, 'P' => 11];
        foreach ($widths as $col => $w) $sheet->getColumnDimension($col)->setWidth($w);

        // F-I = FRC ke-1..4, J-L = sepatu 2024..2026, M = helm, N = kacamata, O = rompi, P = ear plug.
        $tanggal = ['F' => 'tgl_frc', 'G' => 'tgl_frc_2', 'H' => 'tgl_frc_3', 'I' => 'tgl_frc_4',
                    'J' => 'tgl_sepatu', 'K' => 'tgl_sepatu_2', 'L' => 'tgl_sepatu_3',
                    'M' => 'tgl_helm', 'N' => 'tgl_glass', 'O' => 'tgl_vest', 'P' => 'tgl_ear_plug'];
        $startRow = 11;
        foreach ($employees as $idx => $emp) {
            $row   = $startRow + $idx;
            $rowBg = $idx % 2 === 0 ? 'FFFFFF' : 'F5F5F5';
            $p     = $emp->ppe;

            foreach (['A' => $idx + 1, 'B' => strtoupper($emp->nama_lengkap), 'C' => $emp->position?->nama_jabatan ?? '',
                      'D' => $p?->frc ?? '', 'E' => $p?->safety_shoes ?? ''] as $col => $val) {
                $sheet->setCellValue($col . $row, $val);
                $this->ppeCellStyle($sheet, $col . $row, $rowBg, $col === 'B');
                if (in_array($col, ['A', 'D', 'E'])) $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
            foreach ($tanggal as $col => $field) $this->ppeDateCell($sheet, $col, $row, $p?->$field, $rowBg);
            $sheet->getRowDimension($row)->setRowHeight(14);
        }

        $this->printSettings($sheet, 'B11', 'A10:P10');
        return $this->streamXlsx($wb, 'PPE_' . now()->format('Ymd_His') . '.xlsx');
    }

    // ═══════════════════════════════════════════════════
    // HELPER
    // ═══════════════════════════════════════════════════
    private function karyawanAktif()
    {
        $pid = $this->activeProjectId();
        return Employee::aktif()->with('position')->when($pid, fn($q) => $q->where('project_id', $pid));
    }

    private function rowBg(int $idx): string
    {
        return $idx % 2 === 0 ? self::ROW_ODD : self::ROW_EVEN;
    }

    // Satu sheet tabel standar: judul, header, baris data, warna expired, legenda, pengaturan cetak.
    private function exportTable(string $sheetTitle, string $title, $items, array $columns, string $freeze, string $filePrefix)
    {
        [$wb, $sheet] = $this->newTableSheet($sheetTitle, $title, $columns, $items->count());
        foreach ($items->values() as $idx => $item) {
            $this->writeRow($sheet, self::START_ROW + $idx, $idx + 1, $item, $columns, $this->rowBg($idx));
        }
        $this->finishTableSheet($sheet, $columns, self::START_ROW + $items->count() - 1, $freeze);
        return $this->streamXlsx($wb, $filePrefix . '_' . now()->format('Ymd_His') . '.xlsx');
    }

    private function newTableSheet(string $sheetTitle, string $title, array $columns, int $count): array
    {
        $wb    = new Spreadsheet();
        $sheet = $wb->getActiveSheet()->setTitle($sheetTitle);
        $this->makeTitle($sheet, $title, array_key_last($columns), $count);

        $sheet->getRowDimension(self::HEADER_ROW)->setRowHeight(32);
        foreach (['A' => ['No.', 4]] + $columns as $col => [$label, $width]) {
            $cell = $col . self::HEADER_ROW;
            $sheet->setCellValue($cell, $label);
            $sheet->getStyle($cell)->applyFromArray([
                'font'      => ['name' => 'Arial', 'size' => 9, 'bold' => true, 'color' => ['rgb' => self::HDR_TXT]],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::HDR_BG]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'AAAAAA']]],
            ]);
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        return [$wb, $sheet];
    }

    private function writeRow($sheet, int $row, int $no, $item, array $columns, string $bg): void
    {
        $sheet->setCellValue("A{$row}", $no);
        $this->cellStyle($sheet, "A{$row}", $bg);
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        foreach ($columns as $col => $def) {
            $value = $def[2]($item);
            $type  = $def[3] ?? 'text';
            if ($type === 'date' || $type === 'expiry') {
                $this->setDateCell($sheet, $col, $row, $value, $bg);
                continue;
            }
            if ($type === 'string') {
                $sheet->setCellValueExplicit("{$col}{$row}", $value ?? '—', DataType::TYPE_STRING);
            } else {
                $sheet->setCellValue("{$col}{$row}", $value ?? '—');
            }
            $this->cellStyle($sheet, "{$col}{$row}", $bg, $type === 'bold');
        }
        $sheet->getRowDimension($row)->setRowHeight(15);
    }

    private function finishTableSheet($sheet, array $columns, int $lastRow, string $freeze): void
    {
        foreach ($columns as $col => $def) {
            if (($def[3] ?? null) === 'expiry') $this->addDateConditional($sheet, $col, self::START_ROW, $lastRow);
        }
        $this->makeLegend($sheet, $lastRow + 2);
        $this->printSettings($sheet, $freeze, 'A' . self::HEADER_ROW . ':' . array_key_last($columns) . self::HEADER_ROW);
    }

    private function cellStyle($sheet, string $cell, string $bg, bool $bold = false): void
    {
        $sheet->getStyle($cell)->applyFromArray([
            'font'      => ['name' => 'Arial', 'size' => 9, 'color' => ['rgb' => '1A1A2E'], 'bold' => $bold],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D0D3DC']]],
        ]);
    }

    private function setDateCell($sheet, string $col, int $row, $date, string $bg): void
    {
        if ($date) {
            $d = $date instanceof Carbon ? $date : Carbon::parse($date);
            $sheet->setCellValue($col . $row, ExcelDate::PHPToExcel($d->timestamp));
            $sheet->getStyle($col . $row)->getNumberFormat()->setFormatCode('DD MMM YYYY');
        } else {
            $sheet->setCellValue($col . $row, '—');
        }
        $this->cellStyle($sheet, $col . $row, $bg);
    }

    // Merah = sudah expired, kuning = expired dalam < 30 hari (dihitung Excel saat file dibuka).
    private function addDateConditional($sheet, string $col, int $startRow, int $endRow): void
    {
        $cell = "{$col}{$startRow}";

        $expired = new Conditional();
        $expired->setConditionType(Conditional::CONDITION_EXPRESSION);
        $expired->addCondition("AND(ISNUMBER({$cell}),{$cell}<TODAY())");
        $expired->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFCDD2');
        $expired->getStyle()->getFont()->getColor()->setRGB('B71C1C');
        $expired->getStyle()->getFont()->setBold(true);

        $warning = new Conditional();
        $warning->setConditionType(Conditional::CONDITION_EXPRESSION);
        $warning->addCondition("AND(ISNUMBER({$cell}),{$cell}>=TODAY(),{$cell}<TODAY()+30)");
        $warning->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF9C4');
        $warning->getStyle()->getFont()->getColor()->setRGB('7B5E00');

        $sheet->getStyle("{$col}{$startRow}:{$col}{$endRow}")->setConditionalStyles([$expired, $warning]);
    }

    private function makeTitle($sheet, string $title, string $lastCol, int $count): void
    {
        $projectId    = $this->activeProjectId();
        $projectLabel = $projectId ? strtoupper(Project::find($projectId)?->kode ?? 'UNKNOWN') : 'SEMUA PROJECT';

        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A1', strtoupper($title) . ' — PT. ANDALAS KARYA MULIA (' . $projectLabel . ')');
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['name' => 'Arial', 'size' => 13, 'bold' => true, 'color' => ['rgb' => 'E8A020']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1A1A2E']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->setCellValue('A2', 'Diekspor pada: ' . now()->format('d M Y H:i') . ' WIB  |  Total: ' . $count . ' data');
        $sheet->getStyle('A2')->applyFromArray([
            'font'      => ['name' => 'Arial', 'size' => 9, 'italic' => true, 'color' => ['rgb' => '666666']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(16);
        $sheet->getRowDimension(3)->setRowHeight(6);
    }

    private function makeLegend($sheet, int $row): void
    {
        $sheet->mergeCells("A{$row}:B{$row}");
        $sheet->setCellValue("A{$row}", 'Keterangan Warna:');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(9)->setName('Arial');
        $sheet->getRowDimension($row)->setRowHeight(16);

        $row++;
        $items = [
            ['A', 'B', 'Sudah Expired', 'B71C1C', 'FFCDD2'],
            ['C', 'E', 'Akan Expired < 30 Hari', '7B5E00', 'FFF9C4'],
            ['F', 'G', 'Masih Valid', '1B5E20', 'C8E6C9'],
        ];
        foreach ($items as [$from, $to, $label, $txt, $bg]) {
            $sheet->mergeCells("{$from}{$row}:{$to}{$row}");
            $sheet->setCellValue("{$from}{$row}", $label);
            $sheet->getStyle("{$from}{$row}")->applyFromArray([
                'font'      => ['name' => 'Arial', 'size' => 9, 'bold' => true, 'color' => ['rgb' => $txt]],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
            ]);
        }
        $sheet->getRowDimension($row)->setRowHeight(16);
    }

    private function printSettings($sheet, string $freeze, string $filterRange): void
    {
        $sheet->freezePane($freeze);
        $sheet->setAutoFilter($filterRange);
        $sheet->setShowGridlines(false);
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A3)
            ->setFitToPage(true)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(0.5)->setBottom(0.5)->setLeft(0.4)->setRight(0.4);
        $sheet->getHeaderFooter()->setOddHeader('')->setOddFooter('');
    }

    private function applyPpeHdrStyle($sheet, string $cell, string $bg): void
    {
        $sheet->getStyle($cell)->applyFromArray([
            'font'      => ['name' => 'Arial', 'size' => 9, 'bold' => true],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '888888']]],
        ]);
    }

    private function ppeDateCell($sheet, string $col, int $row, $date, string $bg): void
    {
        if ($date) {
            $d = $date instanceof Carbon ? $date : Carbon::parse($date);
            $sheet->setCellValue($col . $row, ExcelDate::PHPToExcel($d->timestamp));
            $sheet->getStyle($col . $row)->getNumberFormat()->setFormatCode('DD/MM/YYYY');
        } else {
            $sheet->setCellValue($col . $row, '');
        }
        $this->ppeCellStyle($sheet, $col . $row, $bg);
        $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    private function ppeCellStyle($sheet, string $cell, string $bg, bool $bold = false): void
    {
        $sheet->getStyle($cell)->applyFromArray([
            'font'      => ['name' => 'Arial', 'size' => 9, 'bold' => $bold],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
        ]);
    }
}
