<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Department;

class UpdatePkwtSeeder extends Seeder
{
    public function run(): void
    {
        $defaultDept = Department::firstOrCreate(
            ['nama' => 'General'],
            ['kode' => 'GEN']
        );

        $posCache = [];
        $getPos = function($jabatan) use (&$posCache, $defaultDept) {
            if (!$jabatan) return null;
            $key = strtoupper(trim($jabatan));
            if (!isset($posCache[$key])) {
                $pos = Position::whereRaw('UPPER(nama_jabatan) = ?', [$key])->first();
                if (!$pos) {
                    $pos = Position::create([
                        'nama_jabatan'  => $jabatan,
                        'department_id' => $defaultDept->id,
                    ]);
                }
                $posCache[$key] = $pos->id;
            }
            return $posCache[$key];
        };

        $updated = 0;
        $created = 0;

        // ── KARYAWAN DENGAN BADGE ──────────────────────────────────────
        $data = [
            ['AKM-EW-0001', 'Bawono Wijayanto', 'Superintendent', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0003', 'Yasni Pinal', 'Project Control Manager', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0005', 'Josua Wironi Hutasoit', 'QA / QC Manager', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0006', 'Fajar Febrian', 'CEM Manager', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0007', 'M Priyo Sudarmono', 'Senior Supervisor', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0008', 'Irfan Dasmanto', 'Hes Coord', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0009', 'M Alief Darmawansyah', 'HRD', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0010', 'Trio Wasiam', 'Admin IJMS', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0011', 'Martin Fernando Siregar', 'Materialman', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0013', 'Muhammad Rapiqi', 'Operator Cable Locator', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0014', 'Havis Al Rasyid', 'Driver Water Tank', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0015', 'Oki Charles', 'Driver Bus', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0016', 'Jhon Hendri', 'Driver Bus', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0017', 'Supriadi', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0017', 'Supriadi', 'Driver Bus', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0018', 'Ahmad Syah', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0019', 'Azlan Effendi', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0020', 'Budi Handoko', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0021', 'Dedek Susanto', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0023', 'Idris', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0024', 'Lactogen Nainggolan', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0025', 'M Nur', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0026', 'Ratno Wiyono', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0027', 'Satriyon', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0028', 'Almedi Indra', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0029', 'Andre Ismawir', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0030', 'Bahtera Nedi', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0031', 'Andri', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0033', 'Bujang Paman', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0034', 'Deni Ade Putra', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0035', 'Jufrizal', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0036', 'Kiswadi', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0039', 'Naharudin', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0040', 'Nurdianto', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0042', 'Syafri Adi', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0044', 'Taruli Naibaho', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0045', 'Tukino', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0046', 'Yulhendri', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0047', 'Yunasril', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0048', 'Dezita Chandra', 'Driver Fuel Tank', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0050', 'Hendra Feri', 'Driver Water Tank', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0051', 'Robbi Saputra', 'Helper', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0052', 'Weldi Rusdi', 'Flagman', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0053', 'Damar Wulan', 'Flagman', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0054', 'Abdul Rahman', 'Helper', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0055', 'Irvan lutfi', 'Helper', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0056', 'Umar Aris Munandar', 'Helper', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0057', 'Bambang Trisno', 'Helper', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0058', 'Salman Syobri', 'Helper', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0059', 'Wawan Riandra', 'Helper', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0060', 'R Garry Patria', 'Helper Survey', 'X', '2025-10-01', '2025-12-31', null],
            ['AKM-EW-0061', 'Eri Sudharman', 'Helper Cable Locator', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0062', 'Wihandri Sanjaya', 'Helper Survey', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0063', 'Alendra Nauly S', 'Hes Man', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0064', 'Rafico Ade Marta', 'Hes Man', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0065', 'Muhammad Agung Perdana', 'Hes Man', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0066', 'Ramza Iswadi', 'Hes Man', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0067', 'Rijalul Rizki', 'HES Man', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0068', 'Yuda Yulianda Pratama', 'Hes Man', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0069', 'Simon Pangestoe', 'Hes Man', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0070', 'Ade Putra', 'Hesman', 'X', '2025-10-01', '2026-03-31', 'Dua Ribu Dua Puluh Empat'],
            ['AKM-EW-0071', 'Andi sahputra', 'Hes Man', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0072', 'Surya Dinata', 'Hes Man', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0073', 'Evandri Wanda Saputra', 'Instrument Survey', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0074', 'Syahrul', 'Instrument Survey', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0074', 'Syahrul', 'Operator Compactor', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0075', 'Dodi Purnama', 'Operator Compactor', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0076', 'Rio Rinaldo', 'Operator Compactor', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0078', 'Ismet', 'Operator Dozer', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0079', 'Sasnauli Lumban Gaol', 'Operator Dozer', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0080', 'Uun Wahyudi', 'Operator Dozer', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0081', 'Abdullah', 'Operator Excavator', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0082', 'Deny Widyansah', 'Operator Excavator', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0083', 'Fendi Pulianto', 'Operator Excavator', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0084', 'Heriance Sormin', 'Operator Excavator', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0085', 'Rusdi Efendi', 'Operator Excavator', 'X', '2025-10-01', '2026-03-31', 'Dua Ribu Dua Puluh Empat'],
            ['AKM-EW-0086', 'Arif Budiman', 'Operator Crane', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0087', 'Haris Hutabarat', 'Operator Foco Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0088', 'Irvan', 'Operator Lowboy', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0089', 'Masril', 'Operator Motor Grader', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0090', 'Rusdiyanto', 'Operator Motor Grader', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0091', 'Andre Syahputra', 'Pipe Fitter', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0092', 'Suhaimi', 'Pipe Fitter 2', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0093', 'Darmawan', 'Pipe Fitter', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0094', 'Tamba Tua Sipahutar', 'Pipe Fitter 2', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0002', 'Sunarto', 'Construction Manager', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0095', 'Ade Rohimulyadi', 'Security', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0096', 'Fadli', 'PMCOW', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0097', 'Feri Susanto', 'PMCOW', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0098', 'Sahat Maruli Tua Sipahutar', 'PMCOW Mekanikal', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0099', 'Syafri', 'PMCOW', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0100', 'Febi Perama', 'PMCOW', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0101', 'Parwira', 'PMCOW Survey', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0102', 'Jami Marzuki', 'PMCOW', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0103', 'Ridho Ilahi', 'PMCOW', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0104', 'Muhammad Irvan', 'Staff HRD', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0106', 'Yunaidi', 'Billing', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0108', 'Romi Ferizal', 'Rigger', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0109', 'Adek Idana', 'Spotter', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0110', 'Eziza Putra', 'Spotter', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0111', 'Risman', 'Helper', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0112', 'Usman', 'Spotter', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0113', 'Fajri Kurniawan', 'Spotter', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0114', 'Rozzi Yanto', 'helper', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0115', 'M syukri Hanafi', 'Helper', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0116', 'Maruli Sitompul', 'Helper', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0117', 'M.Reval Al-Akhyar', 'spotter', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0118', 'Prengki', 'Spotter', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0119', 'Wendi Riandra', 'Spotter', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0004', 'Satria Afriza', 'HES Manager', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0121', 'Hendri Nanang', 'Supervisor', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0122', 'Ovisma', 'Supervisor', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0123', 'Sri Yono', 'Supervisor', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0124', 'Fachri Azmi', 'Materialman', 'X', '2025-10-27', '2026-03-31', null],
            ['AKM-EW-0125', 'Habibur Ramadhan', 'Swamper', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0126', 'Ilham Fajri Wahyudi', 'Swamper', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0127', 'Muhammad Reno Farezi', 'Swamper', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0128', 'Budi alfaid', 'Welder', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0129', 'M Rizki Fadli', 'Flagman', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0130', 'Deni Suwito', 'Operator Bulldozer', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0131', 'Suparman', 'Operator Dozer', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0132', 'Parlindungan Hutagalung', 'Driver Bus', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0133', 'Dedi Hermanto', 'Driver Bus', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0134', 'Tri Prima Doni', 'Driver Bus', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0136', 'Dedi Syofian', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0137', 'Donald Napitupulu', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0138', 'Jekson Sinaga', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0139', 'Jhon Frengki Siregar', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0140', 'Jonni', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0141', 'Lep Jendi Pane', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0142', 'Mara Halim Siregar', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0143', 'Roni Partomuan Manalu', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0145', 'Anurul Huda', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0146', 'Bustanul Arifin', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0147', 'Chandra', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0148', 'Daswir', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0150', 'Donni Asri', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0152', 'Hendra Mulyadi', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0154', 'Marihot Tinambunan', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0156', 'Sefriandi', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0158', 'Ulyadi', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0159', 'Joni Efendi', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0160', 'Saiputra', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0161', 'Gian Febriandes Pratama', 'Quantity Survey', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0162', 'Dasril', 'Driver Fuel Tank', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0163', 'Andrizal', 'Operator Excavator', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0164', 'Parjuangan Daniel Irwan Sinaga', 'Operator Excavator', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0165', 'Vaiz Hera', 'Pilling Record', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0166', 'Jhonny Yunasri', 'Helper', 'X', '2025-10-01', '2026-09-30', null],
            ['AKM-EW-0167', 'M Husni Ikbal', 'Swamper', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0169', 'Mardiono', 'Helper', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0170', 'M Egi Tirtana', 'Spotter', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0171', 'Julianto', 'Helper', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0172', 'Mhd Azwin', 'Helper', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0174', 'Alfikri', 'Hes Man', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0175', 'Abdul Harif', 'QC Inspector', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0176', 'Hendrik Apianto', 'Materialman', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0177', 'Abdul Haris Nasution', 'Operator Compactor', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0178', 'Nelson Robert Sinurat', 'Operator Compactor', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0180', 'Hariyanto Marbun', 'Operator Crane', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0181', 'Firdaus', 'Operator Excavator', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0182', 'Sawal', 'Operator Motor Grader', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0183', 'Muharel', 'Operator Pad Foot', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0184', 'Dodi', 'Pipe Fitter', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0185', 'Maradu Manalu', 'Pipe Fitter', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0186', 'Muharif Saputra', 'Pipe Fitter 2', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0187', 'Riki Rinaldi', 'Pipe Fitter', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0188', 'Ahmad Hafisuddin Nasution', 'PMCOW Mekanikal', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0189', 'Riko Maziner', 'PMCOW Mekanikal', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0190', 'Devi Andra', 'PMCOW', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0191', 'Romi Agus', 'PMCOW Mekanikal', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0192', 'Muhammad Indra', 'Operator Foco Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0193', 'Sapril Samsudin', 'Flagman', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0194', 'Fahmi Aprianto', 'Helper', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0195', 'Yogi Sumantri Arifin', 'Spotter', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0196', 'Zendri', 'spotter', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0197', 'Muhammad Yasir Siregar', 'Spotter', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0198', 'Willy Septiawan', 'Spotter', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0199', 'nanda Afriyendo', 'Checker Pilling', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0200', 'Khaidir', 'Spotter', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0201', 'Edi Sumadi', 'Supervisor Project', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0202', 'Aprinaldo', 'Swamper', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0203', 'Surya Muarif', 'Swamper', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0204', 'Bobi Suparno', 'Welder', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0205', 'Chandra Kirana', 'Welder', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0206', 'Bagus Bayazid Busthomi', 'Welding Inspector', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0207', 'Yudio Alief Putra', 'Admin CEM', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0208', 'Ari Ilham', 'Admin Construction', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0209', 'Zulfikar', 'PMCOW Mekanikal', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0210', 'Rheza Fahlevi', 'Dokumen Control', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0211', 'Zainul Ihkwan', 'Admin CCMS', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0212', 'Jalpi', 'Rigger', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0213', 'Novrizal Bakhris', 'Welder', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0216', 'Reki Wahyudi', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0220', 'Marwan Koneri', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0228', 'Hermanto Butar Butar', 'Welder', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0229', 'Heru Sepriwan', 'HES Man', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0230', 'Dimas Ad', 'Rigger', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0231', 'Mujiono', 'Supervisor', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0231', 'Mujiono', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0232', 'Hasbi Setiawan', 'Helper Cable Locator', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0236', 'Sastro Fernando', 'Helper', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0243', 'Jhon Fahmi', 'Operator Excavator', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0249', 'Muhammad Firdaus', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0254', 'Noval Batubara', 'Operator Trailer', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0256', 'Muhammad Haseb', 'Admin HES', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0258', 'M Syaiful', 'Helper Survey', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0272', 'Ade Trinanda', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0323', 'Ali Zar', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0343', 'Beni Putra', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0361', 'Dimas Septiando', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0369', 'Eri Yusman', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0443', 'Nazarudin', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0617', 'Tommy Pratama', 'Operator Dozer', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0618', 'Bobby', 'Driver Dump Truck', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0628', 'Wahyu Rio Prima', 'Spotter', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0654', 'Pascal Wilmar Dinov', 'Drafter', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0657', 'Husein Al Hafiz', 'Swamper', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0666', 'Syahrullah', 'Driver Bus', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0684', 'Erianda Perdana', 'Admin Material', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0685', 'Aththaariq Muhammad Y', 'AdminQA/QC', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0257', 'Ibnu Fajar Fatihan', 'Inspector CEM', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0651', 'Abdurrahman Ibrahim', 'Rigger', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0677', 'Aman Jefri nainggolan', 'Operator Trailer', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0168', 'Arif Rahman Hakim', 'Swamper', 'X', '2025-10-01', '2026-03-31', null],
            ['AKM-EW-0664', 'Ahmad Baron Marcello', 'Driver Dump Truck', 'X', '2025-11-05', '2026-03-31', null],
            ['AKM-EW-0660', 'Hamdani', 'Driver Dump Truck', 'X', '2025-11-18', '2026-05-18', null],
            ['AKM-EW-0659', 'Riri Putra', 'Driver Dump Truck', 'X', '2025-11-19', '2026-05-18', null],
            ['AKM-EW-0663', 'Agriandi', 'Driver Dump Truck', null, '2025-11-21', '2026-05-18', null],
            ['AKM-EW-0661', 'Andri Saputra', 'Driver Dump Truck', null, '2025-12-11', '2026-06-10', null],
            ['AKM-EW-0166', 'Muhammad Nasir Syahputra', 'Operator IJMS', 'XII', '2026-01-05', '2026-12-31', null],
            ['AKM-EW-0701', 'Sahrul', 'Driver Dump Truck', null, '2026-01-05', '2026-12-31', null],
            ['AKM-EW-0702', 'Indar Toyo', 'Driver Dump Truck', null, '2026-02-16', '2026-08-15', null],
            ['AKM-EW-0703', 'Slamet maryono', 'Driver Dump Truck', null, '2026-02-16', '2026-08-15', null],
            ['AKM-EW-0704', 'Sahdan Mungkur', 'Driver Dump Truck', null, '2026-02-24', '2026-08-22', null],
            ['AKM-EW-0705', 'Rahmat Syah', 'Driver Dump Truck', null, '2026-01-05', '2026-12-31', null],
            ['AKM-EW-0706', 'Victor Maruli Sihombing', 'Driver Dump Truck', null, '2026-03-31', '2026-09-30', null],
            ['AKM-EW-0707', 'Deni Riski Putra', 'Swamper', null, '2026-01-05', '2026-12-31', null],
            ['AKM-EW-0708', 'Andi Syahputra', 'Swamper', null, '2026-01-05', '2026-12-31', null],
            ['AKM-EW-0709', 'Sudarja', 'Operator Dozer', null, '2026-02-02', '2026-07-31', null],
            ['AKM-EW-0710', 'Fiki Gunawan', 'Operator Excavator', null, '2026-01-29', '2026-07-28', null],
            ['AKM-EW-0711', 'Muhammad Qori A', 'Admin CEM', null, '2026-01-05', '2026-12-31', null],
            ['AKM-EW-0662', 'Putra Mulia', 'Driver Dump Truck', null, '2026-01-08', '2026-07-07', null],
            ['AKM-EW-0712', 'Dede Martin', 'Swamper', null, '2026-01-26', '2027-01-23', null],
            ['AKM-EW-0713', 'Yuswardi', 'Swamper', null, '2026-01-26', '2027-01-23', null],
            ['AKM-EW-0621', 'Bobby Nazmi Setiawan', 'HES Man', null, '2026-03-02', '2026-08-31', null],
            ['AKM-EW-0714', 'Dicky Arhandi Maulana', 'Admin CCMS', null, '2026-04-06', '2026-09-30', null],
        ];

        foreach ($data as [$badge, $name, $jabatan, $bln, $start, $end, $nc]) {
            $emp = Employee::where('id_badge', $badge)->first();

            if ($emp) {
                $upd = [];
                if ($bln)   $upd['bln_pkwt']    = $bln;
                if ($start) $upd['start_pkwt']  = $start;
                if ($end)   $upd['end_pkwt']    = $end;
                if ($nc)    $upd['no_contract'] = $nc;
                if (!empty($upd)) { $emp->update($upd); $updated++; }
            } else {
                // Badge belum ada di DB → buat baru
                Employee::create([
                    'id_badge'    => $badge,
                    'nama_lengkap'=> $name,
                    'position_id' => $getPos($jabatan),
                    'status'      => 'AKTIF',
                    'bln_pkwt'    => $bln,
                    'start_pkwt'  => $start,
                    'end_pkwt'    => $end,
                    'no_contract' => $nc,
                ]);
                $this->command->info("➕ Buat baru: [{$badge}] {$name} ({$jabatan})");
                $created++;
            }
        }

        // ── KARYAWAN TANPA BADGE → SKIP, tampilkan warning ────────────
        $noBadge = [
            ['Erdi Musra', 'Operator Foco Truck'],
            ['Petra Virnanto', 'Cost Analyst'],
            ['Ridho Hezalindo', 'Finance Project'],
            ['Sawalinas', 'Logistik'],
            ['Rober Kenedi', 'Security'],
            ['Saripuddin Rambe', 'Helper'],
        ];

        $this->command->warn("");
        $this->command->warn("⚠️  Karyawan berikut TIDAK diimport (belum punya badge):");
        foreach ($noBadge as [$name, $jabatan]) {
            $this->command->warn("   - {$name} ({$jabatan})");
        }
        $this->command->warn("   → Isi badge mereka dulu di modul Data Karyawan, lalu jalankan seeder ini lagi.");

        $this->command->info("");
        $this->command->info("✅ PKWT updated  : {$updated} karyawan");
        $this->command->info("➕ Employee baru : {$created} karyawan");
        $this->command->warn("⚠️  Skip no badge: 6 karyawan");
    }
}
