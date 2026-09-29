<?php
namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AttendanceMachineUser;
use App\Models\AttendanceScan;
use App\Models\Employee;
use App\Models\EmployeeLeave;
use App\Models\Holiday;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Absensi mesin fingerprint (HO): import export mesin (format sheet "sistem") -> rekap
// bulanan per karyawan, export 1 sheet per karyawan persis template manual HR ("DAFTAR ABSENSI").
class AttendanceScanController extends Controller
{
    // Kategori keterangan -> kolom Excel (O..U) + warna legenda template.
    const KATEGORI = [
        'LN'            => ['col' => 'O', 'label' => 'Libur Nasional',                 'color' => 'DA9694'],
        'DL'            => ['col' => 'P', 'label' => 'Dinas Luar',                     'color' => 'FFFF00'],
        'CUTI'          => ['col' => 'Q', 'label' => 'Cuti',                           'color' => '9BBB59'],
        'SAKIT'         => ['col' => 'R', 'label' => 'Sakit',                          'color' => '8064A2'],
        'IJIN_PRIBADI'  => ['col' => 'S', 'label' => 'Ijin Pribadi (Potong Cuti)',     'color' => '4BACC6'],
        'IJIN_NORMATIF' => ['col' => 'T', 'label' => 'Ijin Normatif (Tdk Potong Cuti)', 'color' => '000000'],
        'ALFA'          => ['col' => 'U', 'label' => 'Tidak Absen / Alfa',             'color' => 'FF0000'],
        'HADIR'         => ['col' => null, 'label' => 'Hadir (dianggap normal)',       'color' => null],
    ];
    const KATEGORI_ABSEN = ['CUTI', 'SAKIT', 'IJIN_PRIBADI', 'IJIN_NORMATIF', 'ALFA'];
    const JENIS_CUTI_MAP = [
        'cuti_tahunan' => 'CUTI', 'cuti_haji' => 'CUTI', 'cuti_umroh' => 'CUTI', 'cuti_bersalin' => 'CUTI', 'cuti_haid' => 'CUTI',
        'izin_tanpa_potong' => 'IJIN_NORMATIF', 'izin_tanpa_upah' => 'IJIN_PRIBADI',
    ];
    const HARI  = ['MINGGU', 'SENIN', 'SELASA', 'RABU', 'KAMIS', 'JUMAT', 'SABTU'];
    const BULAN = [1 => 'JANUARI', 'FEBRUARI', 'MARET', 'APRIL', 'MEI', 'JUNI', 'JULI', 'AGUSTUS', 'SEPTEMBER', 'OKTOBER', 'NOPEMBER', 'DESEMBER'];
    const PINK = 'DA9694';

    // ── Data tab "Absensi Mesin" ──
    public function data(Request $request)
    {
        [$tahun, $bulan] = $this->periode($request);
        $lokasi = $request->get('lokasi') ?: null;
        $lokasiList = AttendanceMachineUser::whereNotNull('lokasi')
            ->whereHas('scans', fn($q) => $q->whereYear('tanggal', $tahun)->whereMonth('tanggal', $bulan))
            ->distinct()->orderBy('lokasi')->pluck('lokasi');

        $users = $this->usersBulan($lokasi, $tahun, $bulan)->map(function ($u) use ($tahun, $bulan) {
            $days = $this->hitungBulan($u, $tahun, $bulan);
            $count = fn($fn) => collect($days)->filter($fn)->count();
            return [
                'id'            => $u->id,
                'no_id'         => $u->no_id,
                'nama_mesin'    => $u->nama_mesin,
                'lokasi'        => $this->judulLokasi($u),
                'employee_id'   => $u->employee_id,
                'employee_nama' => $u->employee?->nama_lengkap,
                'lengkap'       => $count(fn($d) => $d['total'] !== null && !$d['kategori']),
                'tidak_lengkap' => $count(fn($d) => $d['tidak_lengkap']),
                'alfa'          => $count(fn($d) => $d['kategori'] === 'ALFA'),
                'keterangan'    => $count(fn($d) => $d['kategori'] && !in_array($d['kategori'], ['ALFA', 'LN'])),
            ];
        })->values();

        $hoId = Project::where('tipe_gaji', 'ho')->value('id');
        return response()->json([
            'lokasi'      => $lokasi,
            'lokasi_list' => $lokasiList,
            'users'       => $users,
            'employees'   => Employee::aktif()->where('project_id', $hoId)->orderBy('nama_lengkap')->get(['id', 'nama_lengkap']),
            'kategori'    => collect(self::KATEGORI)->map(fn($k, $key) => ['key' => $key, 'label' => $k['label']])->values(),
        ]);
    }

    // ── Import file export mesin (sheet "sistem": No. ID, Nama, Tanggal, Jam Kerja, Scan Masuk, Scan Pulang, ...) ──
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xls,xlsx|max:20480',
        ], ['file.required' => 'File wajib dipilih.']);
        $path = $request->file('file')->getRealPath();
        // Judul lokasi sheet diambil dari nama file, mis. "REKAP ... ( AKM WONOSARI ).xls" -> "AKM WONOSARI".
        $lokasi = preg_match_all('/\(([^()]*[A-Za-z][^()]*)\)/', $request->file('file')->getClientOriginalName(), $mm)
            ? strtoupper(trim(preg_replace('/\s+/', ' ', end($mm[1])))) : null;

        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $wb = $reader->load($path);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'File tidak bisa dibaca: ' . $e->getMessage()], 422);
        }

        // Cari sheet data mesin di SEMUA sheet: baris header yang punya kolom "No. ID", "Nama", "Tanggal".
        $norm = fn($v) => preg_replace('/[^a-z]/', '', strtolower((string) $v));
        $col = null; $headerIdx = null; $rows = [];
        foreach ($wb->getAllSheets() as $sheet) {
            $top = $sheet->rangeToArray('A1:Z15', null, false, false, false);
            foreach ($top as $i => $r) {
                $map = [];
                foreach ($r as $c => $v) if ($v !== null && $v !== '') $map[$norm($v)] = $c;
                if (isset($map['noid'], $map['nama'], $map['tanggal'])) { $col = $map; $headerIdx = $i; break; }
            }
            if ($col !== null) { $rows = $sheet->toArray(null, true, false, false); break; }
        }
        if ($col === null) {
            return response()->json(['message' => 'Data mesin tidak ditemukan di sheet mana pun. Pastikan ada sheet dengan kolom "No. ID", "Nama", "Tanggal", "Scan Masuk", "Scan Pulang" (hasil export mesin).'], 422);
        }
        $get = fn($r, $key) => isset($col[$key]) ? ($r[$col[$key]] ?? null) : null;

        $hoId = Project::where('tipe_gaji', 'ho')->value('id');
        $hoEmployees = Employee::aktif()->where('project_id', $hoId)->with('hoDetail')->get(['id', 'nama_lengkap']);
        $stat = ['baris' => 0, 'dilewati' => 0, 'karyawan' => 0, 'tersinkron' => 0, 'belum_cocok' => [], 'periode' => []];

        DB::transaction(function () use ($rows, $headerIdx, $get, $lokasi, $hoEmployees, &$stat) {
            $userCache = [];
            foreach (array_slice($rows, $headerIdx + 1) as $r) {
                $noId = trim((string) $get($r, 'noid'));
                $nama = trim(preg_replace('/\s+/', ' ', (string) $get($r, 'nama')));
                $tgl  = $this->parseTanggal($get($r, 'tanggal'));
                if ($noId === '' || $nama === '' || !$tgl) { if ($noId !== '' || $nama !== '') $stat['dilewati']++; continue; }

                $cacheKey = "{$noId}|{$nama}";
                if (!isset($userCache[$cacheKey])) {
                    $u = AttendanceMachineUser::firstOrNew(['no_id' => $noId, 'nama_mesin' => $nama]);
                    if ($lokasi) $u->lokasi = $lokasi;
                    if (!$u->employee_id && ($emp = $this->cocokkanKaryawan($nama, $hoEmployees))) $u->employee_id = $emp->id;
                    $u->save();
                    $userCache[$cacheKey] = $u->id;
                    $stat['karyawan']++;
                    if ($u->employee_id) $stat['tersinkron']++; else $stat['belum_cocok'][] = $nama;
                }

                $waktu = preg_match_all('/\d{1,2}[:.]\d{2}/', (string) $get($r, 'waktuscan'), $m) ? array_map(fn($t) => $this->parseJam($t), $m[0]) : [];
                $masuk  = $this->parseJam($get($r, 'scanmasuk'))  ?? ($waktu[0] ?? null);
                $pulang = $this->parseJam($get($r, 'scanpulang')) ?? (count($waktu) > 1 ? end($waktu) : null);

                AttendanceScan::updateOrCreate(
                    ['machine_user_id' => $userCache[$cacheKey], 'tanggal' => $tgl->toDateString()],
                    [
                        'jam_kerja'    => trim((string) $get($r, 'jamkerja')) ?: null,
                        'scan_masuk'   => $masuk,
                        'scan_pulang'  => $pulang,
                        'pengecualian' => trim((string) $get($r, 'pengecualian')) ?: null,
                        'waktu_scan'   => trim((string) $get($r, 'waktuscan')) ?: null,
                    ]
                );
                $stat['baris']++;
                $stat['periode'][$tgl->format('Y-m')] = true;
            }
        });
        $stat['periode'] = array_keys($stat['periode']);

        ActivityLog::record('upload', 'Absensi Mesin', $lokasi ?? $request->file('file')->getClientOriginalName(),
            "Import data mesin: {$stat['baris']} baris, {$stat['karyawan']} karyawan ({$stat['tersinkron']} tersinkron), periode " . implode(', ', $stat['periode']));
        return response()->json(['ok' => true, 'lokasi' => $lokasi, ...$stat]);
    }

    public function mapping(Request $request, AttendanceMachineUser $machineUser)
    {
        $data = $request->validate(['employee_id' => 'nullable|exists:employees,id']);
        $machineUser->update(['employee_id' => $data['employee_id'] ?? null]);
        ActivityLog::record('update', 'Absensi Mesin', $machineUser->nama_mesin,
            'Dipetakan ke: ' . ($machineUser->fresh('employee')->employee?->nama_lengkap ?? '(tidak ada)'));
        return response()->json(['ok' => true]);
    }

    // ── Detail harian satu user mesin (untuk edit keterangan) ──
    public function detail(Request $request, AttendanceMachineUser $machineUser)
    {
        [$tahun, $bulan] = $this->periode($request);
        $fmt = fn($m) => $m === null ? null : sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
        $days = collect($this->hitungBulan($machineUser, $tahun, $bulan))->map(fn($d) => [
            'tanggal'          => $d['tanggal']->toDateString(),
            'tgl'              => $d['tanggal']->day,
            'hari'             => self::HARI[$d['tanggal']->dayOfWeek],
            'pink'             => $d['pink'],
            'ada_data'         => $d['ada_data'],
            'masuk'            => $d['masuk'],
            'pulang'           => $d['pulang'],
            'jumlah'           => $fmt($d['jumlah']),
            'kurang'           => $fmt($d['kurang']),
            'lebih'            => $fmt($d['lebih']),
            'kategori'         => $d['kategori'],
            'kategori_sumber'  => $d['sumber'],
            'kategori_manual'  => $d['kategori_manual'],
            'keterangan'       => $d['keterangan'],
            'keterangan_manual'=> $d['keterangan_manual'],
            'tidak_lengkap'    => $d['tidak_lengkap'],
        ])->values();
        return response()->json(['days' => $days]);
    }

    public function updateDay(Request $request, AttendanceMachineUser $machineUser)
    {
        $data = $request->validate([
            'tanggal'    => 'required|date',
            'kategori'   => 'nullable|in:' . implode(',', array_keys(self::KATEGORI)),
            'keterangan' => 'nullable|string|max:150',
        ]);
        AttendanceScan::updateOrCreate(
            ['machine_user_id' => $machineUser->id, 'tanggal' => $data['tanggal']],
            ['kategori' => $data['kategori'] ?? null, 'keterangan' => $data['keterangan'] ?? null]
        );
        ActivityLog::record('update', 'Absensi Mesin', $machineUser->nama_mesin,
            "Keterangan {$data['tanggal']}: " . ($data['kategori'] ?? 'otomatis') . ($data['keterangan'] ? " - {$data['keterangan']}" : ''));
        return response()->json(['ok' => true]);
    }

    // ── Export: 1 file, 1 sheet per karyawan (format "DAFTAR ABSENSI KARYAWAN") ──
    public function export(Request $request)
    {
        [$tahun, $bulan] = $this->periode($request);
        $lokasi = $request->get('lokasi') ?: null;
        $ttd = [
            'hr'       => $request->get('hr') ?: 'M.Ali Nst',
            'gm'       => $request->get('gm') ?: 'Nedriyanto',
            'direktur' => $request->get('direktur') ?: 'Rahmat Sjukri',
        ];
        $users = $this->usersBulan($lokasi, $tahun, $bulan);
        if ($users->isEmpty()) abort(404, 'Belum ada data mesin untuk periode ini.');

        $wb = new Spreadsheet();
        $wb->removeSheetByIndex(0);
        $wb->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
        $usedNames = [];
        foreach ($users as $u) {
            $title = substr(trim(preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', strtoupper($u->nama_mesin))), 0, 28) ?: "ID {$u->no_id}";
            $base = $title; $n = 2;
            while (isset($usedNames[strtolower($title)])) $title = substr($base, 0, 26) . ' ' . $n++;
            $usedNames[strtolower($title)] = true;
            $this->buildSheet($wb->createSheet()->setTitle($title), $u, $tahun, $bulan, $ttd);
        }
        $wb->setActiveSheetIndex(0);

        $namaBulan = self::BULAN[$bulan];
        ActivityLog::record('export', 'Absensi Mesin', $lokasi ?? 'Semua lokasi', "Export rekap absensi {$namaBulan} {$tahun} ({$users->count()} karyawan)");
        $file = 'Rekap_Absensi_' . trim(preg_replace('/[^A-Za-z0-9]+/', '_', $lokasi ?? 'HO'), '_') . "_{$namaBulan}_{$tahun}.xlsx";
        return response()->streamDownload(fn() => (new Xlsx($wb))->save('php://output'), $file,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    // ════════════════════════════════════════════════════════
    private function periode(Request $request): array
    {
        return [(int) $request->get('tahun', now()->year), max(1, min(12, (int) $request->get('bulan', now()->month)))];
    }

    // Judul lokasi baris 4 sheet: dari nama file saat import, kalau tidak ada dari Lokasi Kerja HO karyawan.
    private function judulLokasi(AttendanceMachineUser $u): string
    {
        if ($u->lokasi) return $u->lokasi;
        $lk = strtoupper((string) $u->employee?->hoDetail?->lokasi_kerja);
        return match (true) {
            str_contains($lk, 'TUNAS JAYA') => 'AKM -TUNAS JAYA',
            str_contains($lk, 'WONOSARI')   => 'AKM - WONOSARI',
            default                         => 'HEAD OFFICE',
        };
    }

    private function usersBulan(?string $lokasi, int $tahun, int $bulan)
    {
        return AttendanceMachineUser::with('employee.position', 'employee.atasan', 'employee.hoDetail')
            ->when($lokasi, fn($q) => $q->where('lokasi', $lokasi))
            ->whereHas('scans', fn($q) => $q->whereYear('tanggal', $tahun)->whereMonth('tanggal', $bulan))
            ->get()
            ->sortBy(fn($u) => [is_numeric($u->no_id) ? (int) $u->no_id : PHP_INT_MAX, $u->no_id])
            ->values();
    }

    // Cocokkan nama di mesin ke karyawan HO. Bertingkat, ambil hanya kalau hasilnya tepat 1 orang:
    // 1) sama persis (abaikan spasi/tanda baca: "ZUL APRIL" = "ZULAPRIL")
    // 2) semua kata di nama mesin ada di nama karyawan ("IRFAN TAHER" -> "Muhammad Irfan Taher")
    // 3) kata boleh singkatan: awalan ("RAHMAD SJ" -> "Rahmad Sjukri") atau huruf berurutan ("ALI NST" -> "M. Ali Nasution").
    private function cocokkanKaryawan(string $namaMesin, $employees): ?Employee
    {
        $tokens = fn($s) => array_values(array_filter(explode(' ', trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z ]/', ' ', strtolower((string) $s)))))));
        $target = $tokens($namaMesin);
        if (!$target) return null;
        $calon = $employees->map(fn($e) => ['e' => $e, 'names' => array_filter([$tokens($e->nama_lengkap), $tokens($e->hoDetail?->nama_ktp)])]);

        $pilih = function (callable $cocok) use ($calon) {
            $hit = $calon->filter(fn($c) => collect($c['names'])->contains(fn($t) => $cocok($t)));
            return $hit->count() === 1 ? $hit->first()['e'] : null;
        };
        $subseq = function (string $pendek, string $panjang): bool {
            if ($pendek === '' || $pendek[0] !== $panjang[0]) return false;
            $i = 0;
            foreach (str_split($panjang) as $ch) if ($i < strlen($pendek) && $ch === $pendek[$i]) $i++;
            return $i === strlen($pendek);
        };
        $semuaKata = fn(array $t, callable $sama) => collect($target)->every(fn($m) => collect($t)->contains(fn($k) => $sama($m, $k)));
        $singkatan = fn($m, $k) => $m === $k || str_starts_with($k, $m) || (strlen($m) >= 3 && $subseq($m, $k));
        // Samakan variasi ejaan: RAHMAD=RAHMAT, RONNY=RONI, DJOKO=JOKO, SOEKARNO=SUKARNO.
        $ejaan = fn($w) => preg_replace(['/dj/', '/tj/', '/oe/', '/(.)\1+/', '/y$/', '/d$/'], ['j', 'c', 'u', '$1', 'i', 't'], $w);

        return $pilih(fn($t) => implode('', $t) === implode('', $target))
            ?? $pilih(fn($t) => $semuaKata($t, fn($m, $k) => $m === $k))
            ?? $pilih(fn($t) => $semuaKata($t, $singkatan))
            ?? $pilih(fn($t) => $semuaKata($t, fn($m, $k) => $singkatan($ejaan($m), $ejaan($k))));
    }

    private function parseTanggal($v): ?Carbon
    {
        if ($v === null || $v === '') return null;
        if (is_numeric($v)) {
            try { return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $v))->startOfDay(); } catch (\Throwable) { return null; }
        }
        $s = strtr(trim((string) $v), ['Mei' => 'May', 'Agu' => 'Aug', 'Agt' => 'Aug', 'Okt' => 'Oct', 'Des' => 'Dec', 'Nop' => 'Nov']);
        foreach (['d-M-y', 'd-M-Y', 'd/m/Y', 'd/m/y', 'Y-m-d', 'd-m-Y', 'd M Y'] as $f) {
            try { $d = Carbon::createFromFormat('!' . $f, $s); if ($d && $d->year > 2000) return $d; } catch (\Throwable) {}
        }
        return null;
    }

    private function parseJam($v): ?string
    {
        if ($v === null || $v === '') return null;
        if (is_numeric($v) && (float) $v > 0 && (float) $v < 1) {
            $m = (int) round((float) $v * 1440);
            return sprintf('%02d:%02d', intdiv($m, 60) % 24, $m % 60);
        }
        return preg_match('/(\d{1,2})[:.](\d{2})/', (string) $v, $m) ? sprintf('%02d:%02d', (int) $m[1], (int) $m[2]) : null;
    }

    private function menit(?string $jam): ?int
    {
        return $jam ? ((int) substr($jam, 0, 2)) * 60 + (int) substr($jam, 3, 2) : null;
    }

    // Hitung semua hari dalam bulan untuk satu user mesin.
    private function hitungBulan(AttendanceMachineUser $u, int $tahun, int $bulan): array
    {
        $awal  = Carbon::create($tahun, $bulan, 1);
        $akhir = $awal->copy()->endOfMonth();
        $scans = AttendanceScan::where('machine_user_id', $u->id)->whereBetween('tanggal', [$awal->toDateString(), $akhir->toDateString()])
            ->get()->keyBy(fn($s) => $s->tanggal->toDateString());
        $holidays = Holiday::whereBetween('tanggal', [$awal->toDateString(), $akhir->toDateString()])->get()
            ->keyBy(fn($h) => Carbon::parse($h->tanggal)->toDateString());
        $leaves = $u->employee_id
            ? EmployeeLeave::where('employee_id', $u->employee_id)
                ->where('tanggal_mulai', '<=', $akhir->toDateString())->where('tanggal_selesai', '>=', $awal->toDateString())->get()
            : collect();

        $out = [];
        for ($d = $awal->copy(); $d->lte($akhir); $d->addDay()) {
            $key = $d->toDateString();
            $leave = $leaves->first(fn($l) => $l->tanggal_mulai->toDateString() <= $key && $l->tanggal_selesai->toDateString() >= $key);
            $out[] = $this->hitungHari($d->copy(), $scans->get($key), $holidays->get($key), $leave);
        }
        return $out;
    }

    private function hitungHari(Carbon $tgl, ?AttendanceScan $scan, ?Holiday $libur, ?EmployeeLeave $leave): array
    {
        $dow = $tgl->dayOfWeek;
        $minggu = $dow === Carbon::SUNDAY;
        $normal    = $minggu ? null : ($dow === Carbon::SATURDAY ? 240 : 420);
        $istirahat = $minggu ? null : ($dow === Carbon::SATURDAY ? 0 : 120);
        $masuk = $scan?->scan_masuk; $pulang = $scan?->scan_pulang;

        // Prioritas kategori: input manual > hari libur > cuti/izin HRIS > pengecualian mesin > otomatis alfa.
        $kategori = null; $sumber = null; $ket = null;
        if ($scan?->kategori) {
            $kategori = $scan->kategori; $sumber = 'manual';
        } elseif ($libur) {
            $kategori = 'LN'; $sumber = 'libur'; $ket = strtoupper($libur->keterangan);
        } elseif (!$minggu && $leave && isset(self::JENIS_CUTI_MAP[$leave->jenis])) {
            $kategori = self::JENIS_CUTI_MAP[$leave->jenis]; $sumber = 'cuti';
            $ket = $leave->keterangan ?: (EmployeeLeaveController::JENIS_LABELS[$leave->jenis] ?? null);
        } elseif (!$minggu && $scan?->pengecualian && ($k = $this->kategoriPengecualian($scan->pengecualian))) {
            $kategori = $k; $sumber = 'mesin'; $ket = $scan->pengecualian;
        } elseif (!$minggu && $scan && !$masuk && !$pulang) {
            $kategori = 'ALFA'; $sumber = 'otomatis'; $ket = 'TIDAK ABSEN';
        }
        if ($libur && !$ket) $ket = strtoupper($libur->keterangan);

        $total = $jumlah = $kurang = $lebih = null; $tidakLengkap = false; $missing = [];
        $mIn = $this->menit($masuk); $mOut = $this->menit($pulang);
        $lengkap = $mIn !== null && $mOut !== null && $mOut > $mIn;

        if ($kategori === 'LN' || ($minggu && !$kategori)) {
            // Libur/Minggu: kosong, kecuali ada scan lengkap (masuk di hari libur = kelebihan jam).
            if ($lengkap) { $total = $mOut - $mIn; $jumlah = $total; $kurang = 0; $lebih = $total; $istirahat = 0; $normal = null; }
            else { $istirahat = null; $normal = null; }
        } elseif (in_array($kategori, self::KATEGORI_ABSEN)) {
            $total = 0; $istirahat = 0; $jumlah = 0; $kurang = $normal; $lebih = 0;
        } elseif (in_array($kategori, ['DL', 'HADIR'])) {
            if ($lengkap) { $total = $mOut - $mIn; $jumlah = max(0, $total - $istirahat); $lebih = max(0, $jumlah - $normal); $kurang = 0; }
            else { $jumlah = $normal; $total = $normal + $istirahat; $kurang = 0; $lebih = 0; }
        } elseif ($lengkap) {
            $total = $mOut - $mIn; $jumlah = max(0, $total - $istirahat);
            $kurang = max(0, $normal - $jumlah); $lebih = max(0, $jumlah - $normal);
        } elseif ($masuk || $pulang) {
            $tidakLengkap = true; $missing = $masuk ? ['H'] : ['G'];
            $ket = $ket ?? 'SCAN TIDAK LENGKAP';
        }

        return [
            'tanggal' => $tgl, 'minggu' => $minggu, 'pink' => $minggu || $kategori === 'LN', 'ada_data' => (bool) $scan,
            'masuk' => $masuk, 'pulang' => $pulang,
            'total' => $total, 'istirahat' => $istirahat, 'normal' => $normal, 'jumlah' => $jumlah, 'kurang' => $kurang, 'lebih' => $lebih,
            'kategori' => $kategori, 'sumber' => $sumber, 'kategori_manual' => $scan?->kategori,
            'keterangan' => $scan?->keterangan ?: $ket, 'keterangan_manual' => $scan?->keterangan,
            'tidak_lengkap' => $tidakLengkap, 'missing' => $missing,
        ];
    }

    private function kategoriPengecualian(string $p): ?string
    {
        $p = strtolower($p);
        return match (true) {
            str_contains($p, 'sakit')                                            => 'SAKIT',
            str_contains($p, 'dinas') || preg_match('/\bdl\b/', $p) === 1        => 'DL',
            str_contains($p, 'cuti')                                             => 'CUTI',
            str_contains($p, 'izin') || str_contains($p, 'ijin')                 => 'IJIN_PRIBADI',
            str_contains($p, 'alfa') || str_contains($p, 'alpa')                 => 'ALFA',
            default => null,
        };
    }

    // ── Satu sheet "DAFTAR ABSENSI KARYAWAN" (layout template HR: header R1-R8, data R9-R39, jumlah R40, legenda/ttd R41-R49) ──
    private function buildSheet(Worksheet $s, AttendanceMachineUser $u, int $tahun, int $bulan, array $ttd): void
    {
        $lokasi    = $this->judulLokasi($u);
        $emp       = $u->employee;
        $namaUpper = strtoupper($u->nama_mesin);
        $jabatan   = strtoupper($emp?->position?->nama_jabatan ?? '-');
        $bulanNama = self::BULAN[$bulan];
        $lastDay   = Carbon::create($tahun, $bulan, 1)->daysInMonth;
        $days      = $this->hitungBulan($u, $tahun, $bulan);
        $title     = fn($v) => ucwords(strtolower(trim((string) $v)));
        $fill      = fn($range, $rgb) => $s->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($rgb);
        $center    = ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true];

        foreach (['A' => 5, 'B' => 12, 'C' => 5, 'D' => 15, 'E' => 7, 'F' => 14, 'G' => 9, 'H' => 9, 'I' => 12, 'J' => 11, 'K' => 9.5,
                  'L' => 11.5, 'M' => 13.5, 'N' => 14, 'O' => 7.5, 'P' => 7.5, 'Q' => 7, 'R' => 7.5, 'S' => 7.5, 'T' => 7.5, 'U' => 7, 'V' => 44] as $c => $w) {
            $s->getColumnDimension($c)->setWidth($w);
        }

        // Judul R1-R5
        $s->setCellValue('A1', 'PT. ANDALAS KARYA MULIA');
        $s->getStyle('A1')->getFont()->setBold(true)->setSize(22);
        $s->setCellValue('A2', 'Contractor-Supplier-Construction & Heavy Equipment Rental');
        $s->getStyle('A2')->getFont()->setBold(true)->setSize(9);
        $s->mergeCells('G3:V3')->setCellValue('G3', 'DAFTAR ABSENSI  KARYAWAN PT. ANDALAS KARYA MULIA');
        $s->mergeCells('G4:V4')->setCellValue('G4', $lokasi);
        $s->getStyle('G3:V4')->applyFromArray(['font' => ['bold' => true, 'size' => 14], 'alignment' => $center]);
        $s->setCellValue('A5', $namaUpper);
        $s->getStyle('A5')->getFont()->setBold(true)->setSize(14);

        // Header tabel R6-R8
        $header = [
            'A6:A8' => 'NO.', 'B6:B8' => 'HARI', 'C6:E8' => 'TANGGAL / BULAN / TAHUN', 'F6:F8' => 'JABATAN',
            'G6:N6' => "1 S/D {$lastDay} {$bulanNama} {$tahun}", 'O6:U6' => 'KETERANGAN', 'V6:V8' => 'KETERANGAN',
            'G7:G8' => 'IN/MASUK', 'H7:H8' => 'OUT / PULANG', 'I7:I8' => 'TOTAL JAM KERJA (SEBELUM POTONG JAM ISTIRAHAT)',
            'J7:J8' => 'ISTIRAHAT', 'K7:K8' => 'NORMAL  JAM KERJA', 'L7:L8' => 'JUMLAH JAM KERJA SEHARI',
            'M7:N7' => 'KELEBIHAN / KEKURANGAN JAM KERJA', 'M8' => 'KEKURANGAN JAM KERJA SEHARI', 'N8' => 'KELEBIHAN JAM KERJA SEHARI',
            'O7:O8' => 'LN ( LIBUR NASIONAL )', 'P7:P8' => 'DL ( DINAS LUAR )', 'Q7:Q8' => 'CUTI', 'R7:R8' => 'SAKIT',
            'S7:S8' => 'IJIN PRIBADI POTONG CUTI', 'T7:T8' => 'IJIN TDK POT CUTI ( NORMATIF )', 'U7:U8' => 'TDK ABSEN/ALFA',
        ];
        foreach ($header as $range => $text) {
            if (str_contains($range, ':')) $s->mergeCells($range);
            $s->setCellValue(explode(':', $range)[0], $text);
        }
        $s->getStyle('A6:V8')->applyFromArray(['font' => ['bold' => true, 'size' => 8], 'alignment' => $center]);
        $fill('A6:V8', 'F2F2F2');
        $s->getStyle('G6')->getFont()->setSize(13);
        $s->getStyle('O6')->getFont()->setSize(11);
        $fill('K7', 'EBF1DE');
        $fill('L7', '000000'); $s->getStyle('L7')->getFont()->getColor()->setRGB('FFFFFF');
        $fill('M7', 'F2DCDB'); $fill('M8', 'FFFF00'); $fill('N8', 'D9D9D9');
        foreach (['O' => 'F2DCDB', 'P' => 'FFFF00', 'Q' => '9BBB59', 'R' => '8064A2', 'S' => '4BACC6', 'T' => '000000', 'U' => 'FF0000'] as $c => $rgb) $fill("{$c}7", $rgb);
        $s->getStyle('T7')->getFont()->getColor()->setRGB('FFFFFF');

        // Data R9-R39
        $timeCols = ['I' => 'total', 'J' => 'istirahat', 'K' => 'normal', 'L' => 'jumlah', 'M' => 'kurang', 'N' => 'lebih'];
        for ($i = 0; $i < 31; $i++) {
            $row = 9 + $i;
            $s->getRowDimension($row)->setRowHeight(20);
            if ($i >= $lastDay) continue;
            $d = $days[$i]; $tgl = $d['tanggal'];
            $s->fromArray([$i + 1, self::HARI[$tgl->dayOfWeek], $tgl->day, $bulanNama, $tahun, $jabatan], null, "A{$row}");
            if ($d['masuk'])  $s->setCellValue("G{$row}", $d['masuk']);
            if ($d['pulang']) $s->setCellValue("H{$row}", $d['pulang']);
            foreach ($timeCols as $c => $k) {
                if ($d[$k] !== null) $s->setCellValue("{$c}{$row}", $d[$k] / 1440);
            }
            $kat = $d['kategori'];
            if ($kat && ($kc = self::KATEGORI[$kat]['col'] ?? null)) $s->setCellValue("{$kc}{$row}", 1);
            if ($d['keterangan']) $s->setCellValue("V{$row}", $d['keterangan']);

            if ($d['pink']) {
                $fill("A{$row}:V{$row}", self::PINK);
            } elseif ($kat && self::KATEGORI[$kat]['color'] && $kat !== 'LN') {
                $fill("G{$row}:H{$row}", self::KATEGORI[$kat]['color']);
            }
            foreach ($d['missing'] as $c) $fill("{$c}{$row}", 'FF0000');
            if (in_array($kat, ['SAKIT', 'ALFA'])) $s->getStyle("V{$row}")->getFont()->setBold(true);
        }
        $s->getStyle('A9:V39')->getFont()->setSize(12);
        $s->getStyle('A9:A39')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $s->getStyle('C9:C39')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $s->getStyle('D9:V39')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $s->getStyle('A6:V40')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $s->getStyle('I9:N40')->getNumberFormat()->setFormatCode('[hh]:mm');

        // JUMLAH R40
        $s->mergeCells('A40:F40')->setCellValue('A40', 'JUMLAH');
        foreach (['I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U'] as $c) $s->setCellValue("{$c}40", "=SUM({$c}9:{$c}39)");
        $s->getStyle('A40:V40')->applyFromArray(['font' => ['bold' => true, 'size' => 12], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);
        $fill('A40:V40', 'D9D9D9');
        $s->getStyle('A6:V40')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $s->getStyle('A6:V40')->getBorders()->getOutline()->setBorderStyle(Border::BORDER_MEDIUM);

        // Legenda R41-R48
        $s->setCellValue('B41', 'KETERANGAN :');
        $s->getStyle('B41')->getFont()->setBold(true)->setUnderline(true);
        $legend = [['LN ( LIBUR NASIONAL )', self::PINK], ['DINAS LUAR', 'FFFF00'], ['CUTI', '9BBB59'], ['SAKIT', '8064A2'],
                   ['IJIN PRIBADI ( POTONG CUTI )', '4BACC6'], ['IJIN NORMATIF TIDAK POT CUTI', '000000'], ['TIDAK ABSEN /ALFA', 'FF0000']];
        foreach ($legend as $i => [$label, $rgb]) {
            $r = 42 + $i;
            $s->setCellValue("B{$r}", '-')->setCellValue("C{$r}", $label);
            $fill("B{$r}", $rgb);
            $s->getStyle("B{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $s->getStyle("B{$r}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }

        // Tanda tangan
        $s->setCellValue('O41', 'Karyawan')->setCellValue('S41', 'Atasan langsung');
        $s->setCellValue('O44', $title($u->nama_mesin))->setCellValue('S44', $emp?->atasan ? $title($emp->atasan->nama_lengkap) : '');
        $s->setCellValue('Q45', 'Diketahui')->setCellValue('V45', 'Disetujui');
        $s->setCellValue('O48', $ttd['hr'])->setCellValue('S48', $ttd['gm'])->setCellValue('V48', $ttd['direktur']);
        $s->setCellValue('O49', 'HR,Legal Mngr')->setCellValue('S49', 'GM')->setCellValue('V49', 'Direktur');
        foreach (['O44', 'S44', 'O48', 'S48', 'V48'] as $c) $s->getStyle($c)->getFont()->setBold(true);
        $s->getStyle('V45:V49')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        $s->mergeCells('A49:I49')->setCellValue('A49', "Sumber : Payroll / Rekap Kehadiran Karyawan Staff {$bulanNama}  {$tahun}");
        $s->getStyle('A49')->getFont()->setBold(true)->setSize(9);
        $fill('A49:I49', 'D9D9D9');

        // Tinggi baris & cetak
        $s->getRowDimension(1)->setRowHeight(32);
        foreach ([3, 4, 5] as $r) $s->getRowDimension($r)->setRowHeight(22);
        $s->getRowDimension(6)->setRowHeight(22);
        $s->getRowDimension(7)->setRowHeight(42);
        $s->getRowDimension(8)->setRowHeight(42);
        $s->getRowDimension(40)->setRowHeight(20);
        $s->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)->setFitToHeight(1);
        $s->getPageSetup()->setPrintArea('A1:V49');
        $s->freezePane('A9');
    }
}
