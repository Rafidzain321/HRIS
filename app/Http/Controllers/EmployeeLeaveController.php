<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExcelReport;
use App\Models\ActivityLog;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeLeave;
use App\Models\Holiday;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class EmployeeLeaveController extends Controller
{
    use ExcelReport;

    const JATAH_TAHUNAN = 12;
    // "Cuti Berlebih" (Pasal PP ttg kelebihan jatah) — batas maksimal kelebihan pemakaian cuti
    // tahunan yang boleh mengurangi jatah tahun berikutnya.
    const MAKS_BAWA_KE_DEPAN = 12;
    // Pasal 25 PP — Cuti Haji/Umroh cuma 1x seumur bekerja, maksimal 15 hari kerja per pemakaian.
    const MAKS_HAJI_UMROH_HARI = 15;
    // Pasal 27 PP — Izin Tanpa Upah maksimal 5 hari kerja per tahun.
    const MAKS_IZIN_TANPA_UPAH_TAHUN = 5;

    const JENIS_LABELS = [
        'cuti_tahunan'      => 'Cuti Tahunan',
        'izin_tanpa_potong' => 'Izin (Tanpa Potong Cuti)',
        'cuti_haji'         => 'Cuti Ibadah Haji',
        'cuti_umroh'        => 'Cuti Ibadah Umroh',
        'izin_tanpa_upah'   => 'Izin Tanpa Upah',
        'cuti_bersalin'     => 'Cuti Bersalin',
        'cuti_haid'         => 'Cuti Haid',
    ];

    // Pasal 26 PP — sub-alasan Izin Tidak Masuk Kerja Dengan Upah, beserta ketentuan lama izin.
    const KATEGORI_IZIN_LABELS = [
        'menikah'                    => 'Karyawan Menikah (3 hari)',
        'pernikahan_anak'            => 'Pernikahan Anak (2 hari)',
        'istri_melahirkan_keguguran' => 'Istri Melahirkan/Keguguran (2 hari)',
        'keluarga_meninggal'         => 'Suami/Istri/Anak/Ortu/Mertua/Saudara Kandung Meninggal (3 hari)',
        'khitan_baptis_anak'         => 'Pengkhitanan/Pembaptisan Anak (2 hari)',
        'keluarga_serumah_meninggal' => 'Anggota Keluarga Serumah Meninggal (1 hari)',
        'saksi_pengadilan'           => 'Saksi di Pengadilan (sesuai keperluan)',
        'lainnya'                    => 'Lainnya',
    ];

    // Cuti tahunan khusus karyawan Head Office — jatah 12 hari kerja/tahun, reset tiap 1 Januari.
    public function index(Request $request)
    {
        $tahun = (int) $request->get('tahun', now()->year);

        $employees = $this->karyawanAktifHo()
            ->map(fn ($e) => [
                'id' => $e->id, 'nama_lengkap' => $e->nama_lengkap, 'jabatan' => $e->position?->nama_jabatan ?? '-',
                'jatah_efektif' => EmployeeLeave::jatahEfektif($e->id, $tahun, self::JATAH_TAHUNAN, self::MAKS_BAWA_KE_DEPAN),
            ]);

        $leaves = EmployeeLeave::whereYear('tanggal_mulai', $tahun)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->orderByDesc('tanggal_mulai')
            ->get()
            ->map(fn ($l) => [
                'id'              => $l->id,
                'employee_id'     => $l->employee_id,
                'jenis'           => $l->jenis,
                'kategori_izin'   => $l->kategori_izin,
                'tanggal_mulai'   => $l->tanggal_mulai->format('Y-m-d'),
                'tanggal_selesai' => $l->tanggal_selesai->format('Y-m-d'),
                'jumlah_hari'     => $l->jumlah_hari,
                'keterangan'      => $l->keterangan,
                'dicatat_oleh'    => $l->dicatat_oleh,
            ]);

        $holidays = Holiday::whereYear('tanggal', $tahun)->get(['id', 'tanggal', 'keterangan', 'tipe'])
            ->map(fn ($h) => ['id' => $h->id, 'tanggal' => $h->tanggal->format('Y-m-d'), 'keterangan' => $h->keterangan, 'tipe' => $h->tipe]);

        return Inertia::render('Cuti/Index', [
            'employees' => $employees,
            'leaves'    => $leaves,
            'holidays'  => $holidays,
            'tahun'     => $tahun,
            'jatah'     => self::JATAH_TAHUNAN,
        ]);
    }

    // Export Excel gabungan Cuti Tahunan + Kehadiran — 3 sheet: rekap cuti, detail cuti, ringkasan kehadiran semester.
    // % kehadiran di sheet 3 sama dengan EmployeeAttendanceController::semester(): Dinas Luar dihitung masuk.
    public function export(Request $request)
    {
        $tahun     = (int) $request->get('tahun', now()->year);
        $semester  = (int) $request->get('semester', now()->month <= 6 ? 1 : 2);
        ActivityLog::record('export', 'Cuti & Kehadiran', null, "Export Excel cuti semester {$semester}/{$tahun}");
        $bulanAwal = $semester === 1 ? 1 : 7;
        $bulanAkhir = $semester === 1 ? 6 : 12;

        $employees = $this->karyawanAktifHo();

        $leaves = EmployeeLeave::whereYear('tanggal_mulai', $tahun)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->orderBy('employee_id')->orderByDesc('tanggal_mulai')
            ->get();
        $leavesByEmployee = $leaves->groupBy('employee_id');

        $attendanceRows = EmployeeAttendance::where('tahun', $tahun)
            ->whereBetween('bulan', [$bulanAwal, $bulanAkhir])
            ->whereIn('employee_id', $employees->pluck('id'))
            ->get()->groupBy('employee_id');

        $wb = new Spreadsheet();

        // ── SHEET 1: REKAP CUTI ──
        $sheet1 = $wb->getActiveSheet()->setTitle('Rekap Cuti');
        $this->reportTitle($sheet1, "REKAP CUTI TAHUNAN {$tahun} — PT. ANDALAS KARYA MULIA (HEAD OFFICE)", 'F', $employees->count());
        $headers1 = ['A' => ['No.', 4], 'B' => ['Nama Karyawan', 28], 'C' => ['Jabatan', 26], 'D' => ['Jatah (hari)', 13], 'E' => ['Terpakai (hari)', 16], 'F' => ['Sisa (hari)', 14]];
        $this->reportHeaderRow($sheet1, $headers1, 4, 26);
        foreach ($employees as $idx => $e) {
            $row = 5 + $idx;
            $terpakai = $leavesByEmployee->get($e->id, collect())->where('jenis', 'cuti_tahunan')->sum('jumlah_hari');
            $jatahEfektif = EmployeeLeave::jatahEfektif($e->id, $tahun, self::JATAH_TAHUNAN, self::MAKS_BAWA_KE_DEPAN);
            $sisa = $jatahEfektif - $terpakai;
            $this->reportRow($sheet1, $row, $idx, [
                'A' => $idx + 1, 'B' => strtoupper($e->nama_lengkap), 'C' => $e->position?->nama_jabatan ?? '—',
                'D' => $jatahEfektif, 'E' => $terpakai, 'F' => $sisa,
            ], ['A', 'D', 'E', 'F']);
        }
        $sheet1->setAutoFilter("A4:F4");
        $sheet1->freezePane('B5');
        $sheet1->setShowGridlines(false);

        // ── SHEET 2: DETAIL CUTI ──
        $sheet2 = $wb->createSheet()->setTitle('Detail Cuti');
        $this->reportTitle($sheet2, "DETAIL CUTI TAHUNAN {$tahun} — PT. ANDALAS KARYA MULIA (HEAD OFFICE)", 'I', $leaves->count());
        $headers2 = [
            'A' => ['No.', 4], 'B' => ['Nama Karyawan', 28], 'C' => ['Jabatan', 24],
            'D' => ['Jenis', 22], 'E' => ['Kategori Izin', 32], 'F' => ['Tgl Mulai', 14], 'G' => ['Tgl Selesai', 14], 'H' => ['Jumlah Hari', 12],
            'I' => ['Keterangan', 28],
        ];
        $this->reportHeaderRow($sheet2, $headers2, 4, 26);
        $employeesById = $employees->keyBy('id');
        foreach ($leaves as $idx => $l) {
            $row = 5 + $idx;
            $emp = $employeesById->get($l->employee_id);
            $this->reportRow($sheet2, $row, $idx, [
                'A' => $idx + 1, 'B' => strtoupper($emp?->nama_lengkap ?? '—'), 'C' => $emp?->position?->nama_jabatan ?? '—',
                'D' => self::JENIS_LABELS[$l->jenis] ?? $l->jenis,
                'E' => $l->kategori_izin ? (self::KATEGORI_IZIN_LABELS[$l->kategori_izin] ?? $l->kategori_izin) : '—',
                'F' => $l->tanggal_mulai->format('d M Y'), 'G' => $l->tanggal_selesai->format('d M Y'),
                'H' => $l->jumlah_hari, 'I' => $l->keterangan ?? '—',
            ], ['A', 'D', 'E', 'F', 'G', 'H']);
        }
        if ($leaves->isEmpty()) {
            $sheet2->mergeCells('A5:I5');
            $sheet2->setCellValue('A5', 'Belum ada catatan cuti.');
            $sheet2->getStyle('A5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        $sheet2->setAutoFilter('A4:I4');
        $sheet2->freezePane('B5');
        $sheet2->setShowGridlines(false);

        // ── SHEET 3: RINGKASAN KEHADIRAN ──
        $sheet3 = $wb->createSheet()->setTitle('Kehadiran');
        $periodeLabel = $semester === 1 ? 'Jan–Jun' : 'Jul–Des';
        $this->reportTitle($sheet3, "RINGKASAN KEHADIRAN SEMESTER {$semester} ({$periodeLabel}) {$tahun} — PT. ANDALAS KARYA MULIA (HEAD OFFICE)", 'I', $employees->count());
        $headers3 = [
            'A' => ['No.', 4], 'B' => ['Nama Karyawan', 28], 'C' => ['Jabatan', 24],
            'D' => ['Hadir', 10], 'E' => ['Dinas Luar', 11], 'F' => ['Izin', 10], 'G' => ['Sakit', 10], 'H' => ['Alpha', 10],
            'I' => ['% Kehadiran', 14],
        ];
        $this->reportHeaderRow($sheet3, $headers3, 4, 26);
        foreach ($employees as $idx => $e) {
            $row = 5 + $idx;
            $recs  = $attendanceRows->get($e->id, collect());
            $hadir = $recs->sum('hadir'); $dinasLuar = $recs->sum('dinas_luar'); $izin = $recs->sum('izin'); $sakit = $recs->sum('sakit'); $alpha = $recs->sum('alpha');
            $masuk = $hadir + $dinasLuar;
            $dasar = $masuk + $izin + $sakit + $alpha;
            $pct   = $dasar > 0 ? round($masuk / $dasar * 100, 1) . '%' : '—';
            $this->reportRow($sheet3, $row, $idx, [
                'A' => $idx + 1, 'B' => strtoupper($e->nama_lengkap), 'C' => $e->position?->nama_jabatan ?? '—',
                'D' => $hadir, 'E' => $dinasLuar, 'F' => $izin, 'G' => $sakit, 'H' => $alpha, 'I' => $pct,
            ], ['A', 'D', 'E', 'F', 'G', 'H', 'I']);
        }
        $sheet3->setAutoFilter('A4:I4');
        $sheet3->freezePane('B5');
        $sheet3->setShowGridlines(false);

        $wb->setActiveSheetIndex(0);

        return $this->streamXlsx($wb, 'Cuti_Kehadiran_' . now()->format('Ymd_His') . '.xlsx');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id'     => 'required|exists:employees,id',
            'jenis'           => 'nullable|in:' . implode(',', array_keys(self::JENIS_LABELS)),
            'kategori_izin'   => 'nullable|in:' . implode(',', array_keys(self::KATEGORI_IZIN_LABELS)),
            'tanggal_mulai'   => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'keterangan'      => 'nullable|string|max:200',
        ]);
        $data['jenis'] = $data['jenis'] ?? 'cuti_tahunan';
        // kategori_izin cuma relevan buat Izin Tanpa Potong Cuti (Pasal 26) — jenis lain diabaikan.
        $data['kategori_izin'] = $data['jenis'] === 'izin_tanpa_potong' ? ($data['kategori_izin'] ?? 'lainnya') : null;
        $employeeId = (int) $data['employee_id'];
        $tahun      = (int) date('Y', strtotime($data['tanggal_mulai']));

        $jumlahHari = EmployeeLeave::hitungHariKerja($data['tanggal_mulai'], $data['tanggal_selesai']);
        if ($jumlahHari < 1) {
            return back()->withErrors(['tanggal_selesai' => 'Rentang tanggal ini tidak mengandung hari kerja (semua akhir pekan/hari libur).']);
        }
        if ($error = $this->cekAturan($employeeId, $data['jenis'], $tahun, $jumlahHari)) {
            return back()->withErrors($error);
        }

        $leave = EmployeeLeave::create([
            ...$data,
            'jumlah_hari'  => $jumlahHari,
            'dicatat_oleh' => auth()->user()?->name,
        ]);

        $labelJenis = self::JENIS_LABELS[$data['jenis']] ?? $data['jenis'];
        ActivityLog::record('create', 'Cuti', $leave->employee->nama_lengkap ?? '-', "Catat {$labelJenis} {$jumlahHari} hari kerja ({$data['tanggal_mulai']} s/d {$data['tanggal_selesai']})");

        return back()->with('success', "{$labelJenis} {$jumlahHari} hari kerja berhasil dicatat.");
    }

    public function destroy(EmployeeLeave $leave)
    {
        $nama = $leave->employee->nama_lengkap ?? '-';
        $leave->delete();
        ActivityLog::record('delete', 'Cuti', $nama, 'Catatan cuti dihapus');
        return back()->with('success', 'Catatan cuti berhasil dihapus.');
    }

    // Simpan perubahan dari tampilan Grid Bulanan (klik per tanggal). "hapus" diproses dulu
    // (catatan yang kena dipotong/dipecah), lalu "tambah": tanggal berurutan (cuma dipisah akhir
    // pekan/libur) dengan jenis sama digabung jadi satu catatan. Semua atau tidak sama sekali.
    public function grid(Request $request)
    {
        $data = $request->validate([
            'tambah'               => 'array',
            'tambah.*.employee_id' => 'required|integer',
            'tambah.*.tanggal'     => 'required|date_format:Y-m-d',
            'tambah.*.jenis'       => 'required|in:' . implode(',', array_keys(self::JENIS_LABELS)),
            'hapus'                => 'array',
            'hapus.*.employee_id'  => 'required|integer',
            'hapus.*.tanggal'      => 'required|date_format:Y-m-d',
        ]);
        $karyawan = $this->karyawanAktifHo()->keyBy('id');
        $libur    = Holiday::pluck('tanggal')->map(fn ($d) => $d->toDateString())->flip();
        $hariKerja = fn (Carbon $d) => !$d->isWeekend() && !isset($libur[$d->toDateString()]);

        try {
            $jumlah = DB::transaction(function () use ($data, $karyawan, $hariKerja) {
                foreach ($data['hapus'] ?? [] as $h) {
                    if ($karyawan->has($h['employee_id'])) $this->hapusHari((int) $h['employee_id'], $h['tanggal']);
                }

                // Kelompokkan tanggal per karyawan+jenis, lalu gabung yang berurutan jadi rentang.
                $kelompok = [];
                foreach ($data['tambah'] ?? [] as $t) {
                    if ($karyawan->has($t['employee_id']) && $hariKerja(Carbon::parse($t['tanggal']))) {
                        $kelompok[$t['employee_id'] . '|' . $t['jenis']][] = $t['tanggal'];
                    }
                }
                $jumlah = 0;
                foreach ($kelompok as $kunci => $tanggal) {
                    [$employeeId, $jenis] = explode('|', $kunci);
                    sort($tanggal);
                    $rentang = [];
                    foreach (array_unique($tanggal) as $tgl) {
                        $akhir = $rentang ? end($rentang)[1] : null;
                        $antara = $akhir ? Carbon::parse($akhir)->addDay() : null;
                        while ($antara && $antara->toDateString() < $tgl && !$hariKerja($antara)) $antara->addDay();
                        if ($antara && $antara->toDateString() === $tgl) $rentang[count($rentang) - 1][1] = $tgl;
                        else $rentang[] = [$tgl, $tgl];
                    }

                    $nama = $karyawan[$employeeId]->nama_lengkap;
                    foreach ($rentang as [$mulai, $selesai]) {
                        $bentrok = EmployeeLeave::where('employee_id', $employeeId)
                            ->where('tanggal_mulai', '<=', $selesai)->where('tanggal_selesai', '>=', $mulai)->exists();
                        if ($bentrok) {
                            throw ValidationException::withMessages(['grid' => "{$nama}: tanggal {$mulai} s/d {$selesai} sudah ada catatan cuti/izin lain. Muat ulang halaman."]);
                        }
                        $hari = EmployeeLeave::hitungHariKerja($mulai, $selesai);
                        if ($error = $this->cekAturan((int) $employeeId, $jenis, (int) substr($mulai, 0, 4), $hari)) {
                            throw ValidationException::withMessages(['grid' => "{$nama}: " . reset($error)]);
                        }
                        EmployeeLeave::create([
                            'employee_id'     => $employeeId,
                            'jenis'           => $jenis,
                            'kategori_izin'   => $jenis === 'izin_tanpa_potong' ? 'lainnya' : null,
                            'tanggal_mulai'   => $mulai,
                            'tanggal_selesai' => $selesai,
                            'jumlah_hari'     => $hari,
                            'dicatat_oleh'    => auth()->user()?->name,
                        ]);
                        $jumlah++;
                    }
                }
                return $jumlah;
            });
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        $tambah = count($data['tambah'] ?? []);
        $hapus  = count($data['hapus'] ?? []);
        ActivityLog::record('update', 'Cuti', 'Grid Bulanan', "Input grid: {$tambah} hari ditandai, {$hapus} hari dihapus ({$jumlah} catatan baru)");

        return back()->with('success', 'Perubahan cuti berhasil disimpan.');
    }

    // Lepas satu tanggal dari catatan cuti yang mencakupnya: hapus/potong ujung/pecah jadi dua.
    private function hapusHari(int $employeeId, string $tanggal): void
    {
        $leave = EmployeeLeave::where('employee_id', $employeeId)
            ->where('tanggal_mulai', '<=', $tanggal)->where('tanggal_selesai', '>=', $tanggal)->first();
        if (!$leave) return;

        $mulai   = $leave->tanggal_mulai->toDateString();
        $selesai = $leave->tanggal_selesai->toDateString();
        $sebelum = Carbon::parse($tanggal)->subDay()->toDateString();
        $sesudah = Carbon::parse($tanggal)->addDay()->toDateString();

        $potongan = array_filter([
            $mulai < $tanggal ? [$mulai, $sebelum] : null,
            $selesai > $tanggal ? [$sesudah, $selesai] : null,
        ]);
        $leave->delete();
        foreach ($potongan as [$m, $s]) {
            $hari = EmployeeLeave::hitungHariKerja($m, $s);
            if ($hari < 1) continue; // sisa potongan cuma akhir pekan/libur
            // Rapikan ujung yang jatuh di akhir pekan/libur (mis. "6–8 Nov" jadi "6–6 Nov").
            while (EmployeeLeave::hitungHariKerja($m, $m) === 0) $m = Carbon::parse($m)->addDay()->toDateString();
            while (EmployeeLeave::hitungHariKerja($s, $s) === 0) $s = Carbon::parse($s)->subDay()->toDateString();
            EmployeeLeave::create([
                ...$leave->only(['employee_id', 'jenis', 'kategori_izin', 'keterangan', 'dicatat_oleh']),
                'tanggal_mulai' => $m, 'tanggal_selesai' => $s, 'jumlah_hari' => $hari,
            ]);
        }
    }

    // Aturan kuota per jenis (dipakai catat cuti biasa & Grid Bulanan). null = lolos, selain itu [field => pesan].
    private function cekAturan(int $employeeId, string $jenis, int $tahun, int $jumlahHari): ?array
    {
        // Pasal 25 PP — Cuti Haji/Umroh cuma 1x seumur bekerja & maksimal 15 hari kerja.
        if (in_array($jenis, ['cuti_haji', 'cuti_umroh'])) {
            if (EmployeeLeave::sudahPernahPakai($employeeId, $jenis)) {
                return ['jenis' => self::JENIS_LABELS[$jenis] . ' cuma bisa dipakai 1 kali selama karyawan bekerja di perusahaan ini, dan karyawan ini sudah pernah memakainya.'];
            }
            if ($jumlahHari > self::MAKS_HAJI_UMROH_HARI) {
                return ['tanggal_selesai' => self::JENIS_LABELS[$jenis] . ' maksimal ' . self::MAKS_HAJI_UMROH_HARI . ' hari kerja per pemakaian.'];
            }
        }

        // Pasal 27 PP — Izin Tanpa Upah maksimal 5 hari kerja per tahun.
        if ($jenis === 'izin_tanpa_upah') {
            $sudahDipakai = EmployeeLeave::totalHariTahunIni($employeeId, 'izin_tanpa_upah', $tahun);
            if ($sudahDipakai + $jumlahHari > self::MAKS_IZIN_TANPA_UPAH_TAHUN) {
                $sisa = max(0, self::MAKS_IZIN_TANPA_UPAH_TAHUN - $sudahDipakai);
                return ['tanggal_selesai' => "Izin Tanpa Upah maksimal " . self::MAKS_IZIN_TANPA_UPAH_TAHUN . " hari kerja/tahun. Sisa kuota tahun {$tahun}: {$sisa} hari."];
            }
        }

        // "Cuti Berlebih" — karyawan boleh "meminjam" jatah cuti tahunan tahun depan kalau jatah
        // tahun ini sudah habis, TAPI totalnya (jatah tahun ini + pinjaman) dibatasi supaya tidak
        // sampai menembus jatah 2 tahun ke depan sekaligus.
        if ($jenis === 'cuti_tahunan') {
            $jatahEfektifTahunIni = EmployeeLeave::jatahEfektif($employeeId, $tahun, self::JATAH_TAHUNAN, self::MAKS_BAWA_KE_DEPAN);
            $terpakaiTahunIni = EmployeeLeave::totalHariTahunIni($employeeId, 'cuti_tahunan', $tahun);
            $batasMaksimal = $jatahEfektifTahunIni + self::MAKS_BAWA_KE_DEPAN; // jatah tahun ini + maks pinjaman dari jatah tahun depan
            if ($terpakaiTahunIni + $jumlahHari > $batasMaksimal) {
                $sisaBisaDiambil = max(0, $batasMaksimal - $terpakaiTahunIni);
                return ['tanggal_selesai' => "Cuti Tahunan {$tahun} sudah termasuk pinjam dari jatah tahun depan, maksimal {$batasMaksimal} hari (jatah {$jatahEfektifTahunIni} hari + maks pinjam " . self::MAKS_BAWA_KE_DEPAN . " hari). Sisa yang bisa diambil: {$sisaBisaDiambil} hari."];
            }
        }

        return null;
    }
}
