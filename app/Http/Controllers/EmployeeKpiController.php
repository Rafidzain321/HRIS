<?php
// app/Http/Controllers/EmployeeKpiController.php
namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\KpiAppraisal;
use App\Models\KpiAppraisalScore;
use App\Models\KpiCriteria;
use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;

class EmployeeKpiController extends Controller
{
    // HR (permission edit-kpi) atau super-admin boleh kelola penilaian siapa saja. Selain itu,
    // akun yang terhubung ke data karyawan sendiri (users.employee_id) cuma boleh kelola
    // penilaian dirinya sendiri DAN bawahan langsungnya (atasan_id).
    private function canEditKpiFor(int $employeeId): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        if ($user->hasRole('super-admin') || $user->can('edit-kpi')) return true;
        if (!$user->employee_id) return false;
        if ((int) $user->employee_id === $employeeId) return true;

        return Employee::where('id', $employeeId)->where('atasan_id', $user->employee_id)->exists();
    }

    // Penilai yang ditugaskan bebas di form penilaian (reviewer_id) — boleh mengisi skor,
    // di luar aturan atasan-bawahan biasa.
    private function isReviewerOf(KpiAppraisal $appraisal): bool
    {
        $user = auth()->user();
        return $user && $user->employee_id && $appraisal->reviewer_id && (int) $user->employee_id === (int) $appraisal->reviewer_id;
    }

    // Null = lihat semua (HR/super-admin/view-all-kpi). Selain itu, daftar ID: diri sendiri +
    // bawahan langsung + siapa saja yang penilaiannya ditugaskan ke user ini sebagai reviewer.
    private function scopedEmployeeIds(): ?array
    {
        $user = auth()->user();
        if (!$user || $user->hasRole('super-admin') || $user->can('edit-kpi') || $user->can('view-all-kpi')) {
            return null;
        }
        if (!$user->employee_id) return [];

        $bawahanIds = Employee::where('atasan_id', $user->employee_id)->pluck('id')->toArray();
        $reviewOwnerIds = KpiAppraisal::where('reviewer_id', $user->employee_id)
            ->pluck('employee_id')->toArray();

        return array_values(array_unique([(int) $user->employee_id, ...$bawahanIds, ...$reviewOwnerIds]));
    }

    private function employeesPayload()
    {
        $hoProjectId = Project::where('kode', 'ho')->value('id');
        $scopedIds   = $this->scopedEmployeeIds();

        $employees = Employee::aktif()
            ->where('project_id', $hoProjectId)
            ->when($scopedIds !== null, fn ($q) => $q->whereIn('id', $scopedIds))
            ->with('position')
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'position_id', 'department_id', 'atasan_id'])
            ->map(fn ($e) => [
                'id'           => $e->id,
                'nama_lengkap' => $e->nama_lengkap,
                'jabatan'      => $e->position?->nama_jabatan ?? '-',
                'atasan_id'    => $e->atasan_id,
            ]);

        return [$employees, $scopedIds !== null];
    }

    // Daftar penuh karyawan HO (tanpa dibatasi cakupan atasan-bawahan) — dipakai khusus buat
    // pemilihan "Penilai / Reviewer", supaya penugasannya bebas tidak kaku ke struktur hierarki.
    private function allEmployeesPayload()
    {
        $hoProjectId = Project::where('kode', 'ho')->value('id');

        return Employee::aktif()
            ->where('project_id', $hoProjectId)
            ->with('position')
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'position_id', 'atasan_id'])
            ->map(fn ($e) => [
                'id'           => $e->id,
                'nama_lengkap' => $e->nama_lengkap,
                'jabatan'      => $e->position?->nama_jabatan ?? '-',
                'atasan_id'    => $e->atasan_id,
            ]);
    }

    // Periode berjalan sekarang — semester 1 = Jan-Jun, semester 2 = Jul-Des.
    private function currentPeriod(): array
    {
        return [(int) now()->year, now()->month <= 6 ? 1 : 2];
    }

    // Kriteria yang berlaku buat satu karyawan: 20 poin baku (employee_id kosong) + poin
    // tambahan khusus karyawan itu sendiri (beda-beda tiap orang, bukan per jabatan/departemen).
    private function criteriaForEmployee(Employee $employee)
    {
        return KpiCriteria::where('is_active', true)
            ->where(function ($q) use ($employee) {
                $q->whereNull('employee_id')->orWhere('employee_id', $employee->id);
            })
            ->orderBy('section')->orderBy('urutan')
            ->get();
    }

    // Hitung ulang nilai_a, nilai_b, total_nilai, predikat dari skor yang sudah tersimpan.
    // Kriteria yang belum diisi skornya dihitung 0 (konsisten dengan cara kerja SUM di Excel).
    private function recalculate(KpiAppraisal $appraisal): void
    {
        $employee = $appraisal->employee;
        $criteria = $this->criteriaForEmployee($employee);
        $scoresByCriteria = $appraisal->scores()->pluck('nilai', 'kpi_criteria_id');

        $maxA = $criteria->where('section', 'A')->count() * 5;
        $maxB = $criteria->where('section', 'B')->count() * 5;
        $sumA = $criteria->where('section', 'A')->sum(fn ($c) => (int) ($scoresByCriteria[$c->id] ?? 0));
        $sumB = $criteria->where('section', 'B')->sum(fn ($c) => (int) ($scoresByCriteria[$c->id] ?? 0));

        $nilaiA = $maxA > 0 ? round((40 / $maxA) * $sumA, 2) : 0;
        $nilaiB = $maxB > 0 ? round((60 / $maxB) * $sumB, 2) : 0;
        $total  = round($nilaiA + $nilaiB, 2);

        $appraisal->update([
            'nilai_a'     => $nilaiA,
            'nilai_b'     => $nilaiB,
            'total_nilai' => $total,
            'predikat'    => KpiAppraisal::predikatDari($total),
        ]);
    }

    // ── HALAMAN UTAMA (daftar penilaian per periode + dashboard) ──
    public function index(Request $request)
    {
        [$employees, $isSelfOnly] = $this->employeesPayload();
        [$defYear, $defSemester] = $this->currentPeriod();
        $tahun    = (int) $request->get('tahun', $defYear);
        $semester = (int) $request->get('semester', $defSemester);

        $appraisals = KpiAppraisal::with('reviewer')
            ->whereIn('employee_id', $employees->pluck('id'))
            ->where('tahun', $tahun)->where('semester', $semester)
            ->get()->keyBy('employee_id');

        $rows = $employees->map(function ($e) use ($appraisals) {
            $a = $appraisals->get($e['id']);
            return [
                'employee_id'   => $e['id'],
                'nama_lengkap'  => $e['nama_lengkap'],
                'jabatan'       => $e['jabatan'],
                'appraisal_id'  => $a?->id,
                'nilai_a'       => $a?->nilai_a,
                'nilai_b'       => $a?->nilai_b,
                'total_nilai'   => $a?->total_nilai,
                'predikat'      => $a?->predikat,
                'predikat_label'=> KpiAppraisal::predikatLabel($a?->predikat),
                'status'        => $a?->status ?? 'belum_dinilai',
                'reviewer_id'   => $a?->reviewer_id,
                'reviewer_nama' => $a?->reviewer?->nama_lengkap,
            ];
        });

        return Inertia::render('Kpi/Index', [
            'rows'         => $rows,
            'tahun'        => $tahun,
            'semester'     => $semester,
            'is_self_only' => $isSelfOnly,
            'all_employees'=> $this->allEmployeesPayload(),
            'highlight'    => $request->get('highlight'),
        ]);
    }

    // Buka (atau buatkan kalau belum ada) form penilaian satu karyawan untuk satu periode.
    public function openAppraisal(Request $request, Employee $employee)
    {
        [$defYear, $defSemester] = $this->currentPeriod();
        $tahun    = (int) $request->get('tahun', $defYear);
        $semester = (int) $request->get('semester', $defSemester);

        $appraisal = KpiAppraisal::where('employee_id', $employee->id)
            ->where('tahun', $tahun)->where('semester', $semester)->first();

        if (!$appraisal && !$this->canEditKpiFor($employee->id)) {
            abort(403, 'Kamu tidak memiliki akses untuk membuat penilaian baru untuk karyawan ini.');
        }
        if ($appraisal && !$this->canEditKpiFor($employee->id) && !$this->isReviewerOf($appraisal)) {
            abort(403, 'Kamu tidak memiliki akses untuk melihat penilaian ini.');
        }

        if (!$appraisal) {
            $appraisal = KpiAppraisal::create([
                'employee_id' => $employee->id,
                'tahun'       => $tahun,
                'semester'    => $semester,
                'status'      => 'draft',
            ]);
        }

        $criteria = $this->criteriaForEmployee($employee);
        // Pastikan setiap kriteria yang berlaku punya baris skor (kosong dulu kalau belum diisi).
        $existingCriteriaIds = $appraisal->scores()->pluck('kpi_criteria_id')->toArray();
        foreach ($criteria as $c) {
            if (!in_array($c->id, $existingCriteriaIds)) {
                KpiAppraisalScore::create(['kpi_appraisal_id' => $appraisal->id, 'kpi_criteria_id' => $c->id]);
            }
        }

        $scores = $appraisal->scores()->pluck('nilai', 'kpi_criteria_id');

        return Inertia::render('Kpi/AppraisalForm', [
            'appraisal' => [
                'id'          => $appraisal->id,
                'tahun'       => $appraisal->tahun,
                'semester'    => $appraisal->semester,
                'status'      => $appraisal->status,
                'catatan'     => $appraisal->catatan,
                'reviewer_id' => $appraisal->reviewer_id,
                'nilai_a'     => $appraisal->nilai_a,
                'nilai_b'     => $appraisal->nilai_b,
                'total_nilai' => $appraisal->total_nilai,
                'predikat'    => $appraisal->predikat,
            ],
            'employee' => [
                'id'           => $employee->id,
                'nama_lengkap' => $employee->nama_lengkap,
                'jabatan'      => $employee->position?->nama_jabatan ?? '-',
                'departemen'   => $employee->department?->nama ?? '-',
                'id_badge'     => $employee->id_badge,
                'tanggal_masuk'=> $employee->tanggal_masuk?->format('d M Y'),
            ],
            'criteria' => $criteria->map(fn ($c) => [
                'id'           => $c->id,
                'section'      => $c->section,
                'sub_kategori' => $c->sub_kategori,
                'deskripsi'    => $c->deskripsi,
                'is_base'      => $c->employee_id === null,
                'nilai'        => $scores[$c->id] ?? null,
            ]),
            'all_employees' => $this->allEmployeesPayload(),
            'can_edit'      => $this->canEditKpiFor($employee->id) || $this->isReviewerOf($appraisal),
            'can_reopen'    => $this->isAdminSettings(),
        ]);
    }

    // Simpan semua skor kriteria sekaligus (dipanggil tiap kali form disimpan, baik draft
    // maupun submit final).
    public function saveScores(Request $request, KpiAppraisal $appraisal)
    {
        if (!$this->canEditKpiFor($appraisal->employee_id) && !$this->isReviewerOf($appraisal)) {
            return response()->json(['ok' => false, 'message' => 'Kamu tidak memiliki akses untuk mengisi penilaian ini.'], 403);
        }
        if ($appraisal->status === 'submitted') {
            return response()->json(['ok' => false, 'message' => 'Penilaian ini sudah final dan terkunci. Minta HR/super-admin untuk membuka kembali kalau ada yang perlu dikoreksi.'], 422);
        }

        $data = $request->validate([
            'reviewer_id'      => 'nullable|exists:employees,id',
            'catatan'          => 'nullable|string|max:2000',
            'scores'           => 'required|array',
            'scores.*.criteria_id' => 'required|exists:kpi_criteria,id',
            'scores.*.nilai'   => 'nullable|integer|min:1|max:5',
            'submit'           => 'nullable|boolean',
        ]);

        foreach ($data['scores'] as $s) {
            KpiAppraisalScore::updateOrCreate(
                ['kpi_appraisal_id' => $appraisal->id, 'kpi_criteria_id' => $s['criteria_id']],
                ['nilai' => $s['nilai'] ?? null]
            );
        }

        $appraisal->update([
            'reviewer_id' => $data['reviewer_id'] ?? $appraisal->reviewer_id,
            'catatan'     => $data['catatan'] ?? $appraisal->catatan,
        ]);

        $this->recalculate($appraisal);

        if (!empty($data['submit'])) {
            $belumLengkap = collect($data['scores'])->contains(fn ($s) => empty($s['nilai']));
            if ($belumLengkap) {
                return response()->json(['ok' => false, 'message' => 'Semua poin penilaian harus diisi (1-5) sebelum bisa disimpan final.'], 422);
            }
            $appraisal->update(['status' => 'submitted', 'submitted_at' => now()]);
            ActivityLog::record('update', 'Penilaian KPI', $appraisal->employee->nama_lengkap ?? '-', "Penilaian semester {$appraisal->semester}/{$appraisal->tahun} disimpan final — total {$appraisal->fresh()->total_nilai} ({$appraisal->fresh()->predikat})");
            session()->flash('success', "Penilaian {$appraisal->employee->nama_lengkap} berhasil disimpan final.");
        } else {
            ActivityLog::record('update', 'Penilaian KPI', $appraisal->employee->nama_lengkap ?? '-', "Draft penilaian semester {$appraisal->semester}/{$appraisal->tahun} disimpan");
            session()->flash('success', 'Draft penilaian berhasil disimpan.');
        }

        $fresh = $appraisal->fresh();
        return response()->json([
            'ok'      => true,
            'message' => !empty($data['submit']) ? 'Penilaian berhasil disimpan final.' : 'Draft berhasil disimpan.',
            'appraisal' => [
                'status'      => $fresh->status,
                'nilai_a'     => $fresh->nilai_a,
                'nilai_b'     => $fresh->nilai_b,
                'total_nilai' => $fresh->total_nilai,
                'predikat'    => $fresh->predikat,
            ],
        ]);
    }

    public function destroyAppraisal(KpiAppraisal $appraisal)
    {
        if (!$this->canEditKpiFor($appraisal->employee_id)) {
            return response()->json(['ok' => false, 'message' => 'Kamu tidak memiliki akses untuk menghapus penilaian ini.'], 403);
        }
        if ($appraisal->status === 'submitted') {
            return response()->json(['ok' => false, 'message' => 'Penilaian yang sudah final tidak bisa langsung dihapus — buka kembali dulu lewat HR/super-admin.'], 422);
        }

        $nama = $appraisal->employee->nama_lengkap ?? '-';
        $periode = "{$appraisal->semester}/{$appraisal->tahun}";
        $appraisal->delete();

        ActivityLog::record('delete', 'Penilaian KPI', $nama, "Hapus penilaian semester {$periode}");
        session()->flash('success', "Draft penilaian {$nama} berhasil dibuang.");

        return response()->json(['ok' => true]);
    }

    // Buka kembali penilaian yang sudah final — khusus HR/super-admin, dipakai kalau ada
    // salah input yang baru ketahuan setelah disimpan final. Status balik ke draft supaya
    // bisa dikoreksi lalu disimpan final ulang.
    public function reopenAppraisal(KpiAppraisal $appraisal)
    {
        if (!$this->isAdminSettings()) {
            abort(403, 'Hanya HR/super-admin yang bisa membuka kembali penilaian yang sudah final.');
        }
        if ($appraisal->status !== 'submitted') {
            return response()->json(['ok' => false, 'message' => 'Penilaian ini belum final.'], 422);
        }

        $appraisal->update(['status' => 'draft', 'submitted_at' => null]);

        $nama = $appraisal->employee->nama_lengkap ?? '-';
        ActivityLog::record('update', 'Penilaian KPI', $nama, "Buka kembali penilaian semester {$appraisal->semester}/{$appraisal->tahun} (dari final ke draft)");
        session()->flash('success', "Penilaian {$nama} dibuka kembali ke draft.");

        return response()->json(['ok' => true, 'status' => 'draft']);
    }

    // ── KRITERIA PENILAIAN (20 baku + tambahan khusus per karyawan) ──
    private function canManageCriteria(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        if ($this->isAdminSettings()) return true;
        return $user->employee_id && Employee::where('atasan_id', $user->employee_id)->exists();
    }

    // HR/super-admin boleh menambah kriteria untuk karyawan siapa saja. Atasan (bukan HR)
    // hanya boleh menambah untuk bawahan langsungnya sendiri.
    private function canTargetEmployeeForCriteria(int $employeeId): bool
    {
        $user = auth()->user();
        if ($this->isAdminSettings()) return true;
        return $user->employee_id && Employee::where('id', $employeeId)->where('atasan_id', $user->employee_id)->exists();
    }

    public function criteriaIndex()
    {
        if (!$this->canManageCriteria()) {
            abort(403, 'Kamu tidak memiliki akses ke halaman ini.');
        }

        $criteria = KpiCriteria::with('employee')->where('is_active', true)
            ->orderBy('section')->orderBy('employee_id')->orderBy('urutan')->get()
            ->map(fn ($c) => [
                'id'            => $c->id,
                'section'       => $c->section,
                'sub_kategori'  => $c->sub_kategori,
                'deskripsi'     => $c->deskripsi,
                'employee_id'   => $c->employee_id,
                'employee_nama' => $c->employee?->nama_lengkap,
                'is_base'       => $c->employee_id === null,
            ]);

        $user = auth()->user();
        $targetEmployees = $this->isAdminSettings()
            ? $this->allEmployeesPayload()
            : Employee::aktif()->where('atasan_id', $user->employee_id)->with('position')->orderBy('nama_lengkap')
                ->get(['id', 'nama_lengkap', 'position_id'])
                ->map(fn ($e) => ['id' => $e->id, 'nama_lengkap' => $e->nama_lengkap, 'jabatan' => $e->position?->nama_jabatan ?? '-']);

        return Inertia::render('Kpi/CriteriaManage', [
            'criteria'         => $criteria,
            'target_employees' => $targetEmployees,
            'can_edit_text'    => $this->isAdminSettings(),
        ]);
    }

    public function storeCriteria(Request $request)
    {
        if (!$this->canManageCriteria()) {
            abort(403, 'Kamu tidak memiliki akses untuk menambah kriteria.');
        }

        $data = $request->validate([
            'section'      => 'required|in:A,B',
            'sub_kategori' => 'nullable|string|max:100',
            'deskripsi'    => 'required|string|max:500',
            'employee_id'  => 'required|exists:employees,id',
        ]);

        if (!$this->canTargetEmployeeForCriteria((int) $data['employee_id'])) {
            abort(403, 'Kamu hanya bisa menambah kriteria untuk bawahan langsungmu sendiri.');
        }

        $urutan = KpiCriteria::where('section', $data['section'])->max('urutan') + 1;

        $criteria = KpiCriteria::create([
            ...$data,
            'urutan'     => $urutan,
            'created_by' => auth()->id(),
            'is_active'  => true,
        ]);

        $emp = Employee::find($data['employee_id']);
        ActivityLog::record('create', 'Kriteria KPI', $emp?->nama_lengkap, "Tambah kriteria: {$data['deskripsi']}");

        return back()->with('success', 'Kriteria berhasil ditambahkan.');
    }

    // Edit teks kriteria (perbaiki salah ketik) — berlaku untuk 20 kriteria baku maupun
    // tambahan, tapi khusus HR/super-admin (bukan atasan biasa) karena kriteria baku
    // menyangkut semua karyawan sekaligus dan riwayat penilaian yang sudah ada.
    public function updateCriteria(Request $request, KpiCriteria $criteria)
    {
        if (!$this->isAdminSettings()) {
            abort(403, 'Hanya HR/super-admin yang bisa mengubah teks kriteria.');
        }

        $data = $request->validate([
            'sub_kategori' => 'nullable|string|max:100',
            'deskripsi'    => 'required|string|max:500',
        ]);

        $criteria->update($data);
        ActivityLog::record('update', 'Kriteria KPI', $criteria->employee?->nama_lengkap, "Ubah teks kriteria menjadi: {$data['deskripsi']}");

        return back()->with('success', 'Kriteria berhasil diperbarui.');
    }

    // Nonaktifkan (bukan hapus permanen) — biar skor historis yang sudah memakai kriteria ini
    // tetap utuh, cuma tidak dipakai lagi buat penilaian baru ke depan.
    public function destroyCriteria(KpiCriteria $criteria)
    {
        if (!$this->canManageCriteria()) {
            abort(403, 'Kamu tidak memiliki akses untuk menghapus kriteria.');
        }
        if ($criteria->employee_id === null) {
            return response()->json(['ok' => false, 'message' => '20 kriteria baku tidak bisa dihapus.'], 422);
        }
        if (!$this->canTargetEmployeeForCriteria($criteria->employee_id)) {
            return response()->json(['ok' => false, 'message' => 'Kamu tidak memiliki akses untuk menghapus kriteria ini.'], 403);
        }

        $criteria->update(['is_active' => false]);
        ActivityLog::record('delete', 'Kriteria KPI', $criteria->employee?->nama_lengkap, "Nonaktifkan kriteria: {$criteria->deskripsi}");

        return response()->json(['ok' => true]);
    }

    // ── EXPORT EXCEL ──
    public function export(Request $request)
    {
        [$employees] = $this->employeesPayload();
        [$defYear, $defSemester] = $this->currentPeriod();
        $tahun    = (int) $request->get('tahun', $defYear);
        $semester = (int) $request->get('semester', $defSemester);
        $onlyEmployeeId = $request->get('employee_id');

        $employeesModel = Employee::aktif()->whereIn('id', $employees->pluck('id'))
            ->when($onlyEmployeeId, fn ($q) => $q->where('id', $onlyEmployeeId))
            ->with('position')->orderBy('nama_lengkap')->get();

        $appraisals = KpiAppraisal::with(['scores.criteria', 'reviewer'])
            ->whereIn('employee_id', $employeesModel->pluck('id'))
            ->where('tahun', $tahun)->where('semester', $semester)
            ->get()->keyBy('employee_id');

        $predikatColor = ['A' => 'BBDEFB', 'BS' => 'C8E6C9', 'B' => 'C8E6C9', 'C' => 'FFE0B2', 'K' => 'FFCDD2'];

        $wb = new Spreadsheet();

        // ── SHEET 1: RINGKASAN ──
        $sheet1 = $wb->getActiveSheet()->setTitle('Ringkasan');
        $this->kpiExportTitle($sheet1, "RINGKASAN PENILAIAN KPI SEMESTER {$semester} {$tahun} — PT. ANDALAS KARYA MULIA (HEAD OFFICE)", 'H', $employeesModel->count());
        $headers1 = [
            'A' => ['No.', 4], 'B' => ['Nama Karyawan', 26], 'C' => ['Jabatan', 24],
            'D' => ['Nilai A (40%)', 13], 'E' => ['Nilai B (60%)', 13], 'F' => ['Total Nilai', 12],
            'G' => ['Predikat', 16], 'H' => ['Status', 13],
        ];
        $this->kpiExportHeaderRow($sheet1, $headers1, 4);
        foreach ($employeesModel as $idx => $e) {
            $row = 5 + $idx;
            $a = $appraisals->get($e->id);
            $predikatLabel = $a ? (KpiAppraisal::predikatLabel($a->predikat) . " ({$a->predikat})") : '—';
            $this->kpiExportRow($sheet1, $row, $idx, [
                'A' => $idx + 1,
                'B' => strtoupper($e->nama_lengkap),
                'C' => $e->position?->nama_jabatan ?? '—',
                'D' => $a?->nilai_a ?? '—',
                'E' => $a?->nilai_b ?? '—',
                'F' => $a?->total_nilai ?? '—',
                'G' => $predikatLabel,
                'H' => $a ? ($a->status === 'submitted' ? 'Final' : 'Draft') : 'Belum Dinilai',
            ], ['A', 'D', 'E', 'F', 'G', 'H']);
            if ($a?->predikat) {
                $sheet1->getStyle('G' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($predikatColor[$a->predikat] ?? 'FFFFFF');
            }
        }
        $sheet1->setAutoFilter('A4:H4');
        $sheet1->freezePane('B5');
        $sheet1->setShowGridlines(false);

        // ── DATA RINGKAS + GRAFIK DONUT (status & predikat) — disisipkan di kolom J ke
        // kanan supaya tidak bertabrakan dengan tabel utama berapa pun jumlah karyawannya.
        $statusCounts = ['belum_dinilai' => 0, 'draft' => 0, 'submitted' => 0];
        $predikatCounts = ['K' => 0, 'C' => 0, 'B' => 0, 'BS' => 0, 'A' => 0];
        foreach ($employeesModel as $e) {
            $a = $appraisals->get($e->id);
            $statusCounts[$a?->status ?? 'belum_dinilai']++;
            if ($a?->predikat) {
                $predikatCounts[$a->predikat]++;
            }
        }

        $sheet1->getColumnDimension('J')->setWidth(20);
        $sheet1->getColumnDimension('K')->setWidth(10);

        $sheet1->setCellValue('J4', 'Distribusi Status Penilaian');
        $sheet1->getStyle('J4')->getFont()->setBold(true);
        $statusLabels = ['belum_dinilai' => 'Belum Dinilai', 'draft' => 'Draft', 'submitted' => 'Final'];
        $row = 6;
        foreach ($statusLabels as $key => $label) {
            $sheet1->setCellValue("J{$row}", $label);
            $sheet1->setCellValue("K{$row}", $statusCounts[$key]);
            $row++;
        }

        $sheet1->setCellValue('J10', 'Distribusi Predikat');
        $sheet1->getStyle('J10')->getFont()->setBold(true);
        $predikatLabels = ['K' => 'K - Kurang', 'C' => 'C - Cukup', 'B' => 'B - Baik', 'BS' => 'BS - Baik Sekali', 'A' => 'A - Memuaskan'];
        $row = 12;
        foreach ($predikatLabels as $key => $label) {
            $sheet1->setCellValue("J{$row}", $label);
            $sheet1->setCellValue("K{$row}", $predikatCounts[$key]);
            $row++;
        }

        $sheet1->addChart($this->kpiDonutChart(
            'Ringkasan', 'chart_status', 'Distribusi Status Penilaian',
            '$J$6:$J$8', '$K$6:$K$8', 3, 'M4', 'T18'
        ));
        $sheet1->addChart($this->kpiDonutChart(
            'Ringkasan', 'chart_predikat', 'Distribusi Predikat',
            '$J$12:$J$16', '$K$12:$K$16', 5, 'M20', 'T34'
        ));

        // ── SHEET 2: DETAIL PENILAIAN (tiap kriteria per karyawan) ──
        $sheet2 = $wb->createSheet()->setTitle('Detail Penilaian');
        $detailRows = collect();
        foreach ($employeesModel as $e) {
            $a = $appraisals->get($e->id);
            if (!$a) continue;
            foreach ($a->scores as $s) {
                $detailRows->push([
                    'nama'      => $e->nama_lengkap,
                    'jabatan'   => $e->position?->nama_jabatan ?? '—',
                    'section'   => $s->criteria->section,
                    'sub'       => $s->criteria->sub_kategori,
                    'deskripsi' => $s->criteria->deskripsi,
                    'nilai'     => $s->nilai,
                ]);
            }
        }
        $this->kpiExportTitle($sheet2, "DETAIL PENILAIAN KPI SEMESTER {$semester} {$tahun} — PT. ANDALAS KARYA MULIA (HEAD OFFICE)", 'F', $detailRows->count());
        $headers2 = [
            'A' => ['No.', 4], 'B' => ['Nama Karyawan', 26], 'C' => ['Jabatan', 22],
            'D' => ['Section', 10], 'E' => ['Kriteria', 55], 'F' => ['Nilai (1-5)', 11],
        ];
        $hRow2 = 4;
        $this->kpiExportHeaderRow($sheet2, $headers2, $hRow2);
        foreach ($detailRows as $idx => $r) {
            $row = 5 + $idx;
            $label = $r['section'] === 'A' ? 'A. Keselamatan' : ('B. ' . ($r['sub'] ?? 'Produktivitas'));
            $this->kpiExportRow($sheet2, $row, $idx, [
                'A' => $idx + 1, 'B' => strtoupper($r['nama']), 'C' => $r['jabatan'],
                'D' => $label, 'E' => $r['deskripsi'], 'F' => $r['nilai'] ?? '—',
            ], ['A', 'D', 'F']);
        }
        if ($detailRows->isEmpty()) {
            $sheet2->mergeCells("A5:F5");
            $sheet2->setCellValue('A5', 'Belum ada data penilaian.');
            $sheet2->getStyle('A5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        $sheet2->freezePane('B5');
        $sheet2->setAutoFilter("A{$hRow2}:F{$hRow2}");
        $sheet2->setShowGridlines(false);
        $sheet2->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $sheet2->getPageSetup()->setFitToPage(true)->setFitToWidth(1)->setFitToHeight(0);

        $wb->setActiveSheetIndex(0);

        $namaFile = $onlyEmployeeId && $employeesModel->first()
            ? 'KPI_' . str_replace(' ', '_', $employeesModel->first()->nama_lengkap) . '_' . now()->format('Ymd_His') . '.xlsx'
            : 'KPI_' . now()->format('Ymd_His') . '.xlsx';

        $writer = new Xlsx($wb);
        $writer->setIncludeCharts(true);
        return response()->stream(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $namaFile . '"',
            'Cache-Control'       => 'max-age=0',
        ]);
    }

    // Bikin chart donut native Excel dari satu range kategori + satu range nilai di sheet
    // yang sama (dipakai untuk grafik distribusi status & predikat pada export KPI).
    private function kpiDonutChart(string $sheetTitle, string $name, string $titleText, string $catRange, string $valRange, int $count, string $topLeft, string $bottomRight): Chart
    {
        $labels = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'{$sheetTitle}'!{$catRange}", null, $count)];
        $values = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'{$sheetTitle}'!{$valRange}", null, $count)];

        $series = new DataSeries(DataSeries::TYPE_DOUGHNUTCHART, null, [0], [], $labels, $values);
        $plotArea = new PlotArea(null, [$series]);
        $legend = new Legend(Legend::POSITION_RIGHT, null, false);
        $title = new Title($titleText);

        $chart = new Chart($name, $title, $legend, $plotArea);
        $chart->setTopLeftPosition($topLeft);
        $chart->setBottomRightPosition($bottomRight);

        return $chart;
    }

    private function kpiExportTitle($sheet, string $title, string $lastCol, int $count): void
    {
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A1', $title);
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['name' => 'Arial', 'size' => 12, 'bold' => true, 'color' => ['rgb' => 'E8A020']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1A1A2E']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
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

    private function kpiExportHeaderRow($sheet, array $headers, int $hRow): void
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
        $sheet->getRowDimension($hRow)->setRowHeight(28);
    }

    private function kpiExportRow($sheet, int $row, int $idx, array $values, array $centerCols = []): void
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

    // ── STRUKTUR ORGANISASI (atasan-bawahan) ─────────────────────
    // Sebelumnya atasan_id cuma bisa diisi lewat seeder/DB langsung (hardcoded) — sekarang
    // bisa diatur bebas dari sini oleh HR, tanpa perlu sentuh kode. Dipakai buat hierarki
    // notifikasi & cakupan lihat/edit KPI ("bawahan langsung" di scopedEmployeeIds()).
    public function orgStructure()
    {
        if (!$this->isAdminSettings()) {
            abort(403, 'Kamu tidak memiliki akses ke halaman ini.');
        }

        $hoProjectId = Project::where('kode', 'ho')->value('id');
        $employees = Employee::aktif()->where('project_id', $hoProjectId)
            ->with('position')->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'position_id', 'atasan_id'])
            ->map(fn ($e) => [
                'id'           => $e->id,
                'nama_lengkap' => $e->nama_lengkap,
                'jabatan'      => $e->position?->nama_jabatan ?? '-',
                'atasan_id'    => $e->atasan_id,
            ]);

        $doc = \App\Models\OrgStructureDocument::with('uploader')->latest()->first();

        return Inertia::render('Kpi/OrgStructure', [
            'employees' => $employees,
            'document'  => $doc ? [
                'id'           => $doc->id,
                'nama_file'    => $doc->nama_file,
                'mime_type'    => $doc->mime_type,
                'size'         => $doc->size_formatted,
                'uploaded_by'  => $doc->uploader?->name ?? '—',
                'uploaded_at'  => $doc->created_at->format('d M Y H:i'),
            ] : null,
        ]);
    }

    // Dokumen bagan/struktur organisasi resmi (gambar/PDF) — cuma referensi visual, terpisah
    // dari data atasan_id fungsional. Selalu cuma nyimpen SATU dokumen terkini; upload baru
    // otomatis mengganti (menghapus) yang lama.
    public function uploadOrgDocument(Request $request)
    {
        if (!$this->isAdminSettings()) {
            abort(403, 'Kamu tidak memiliki akses untuk mengganti dokumen ini.');
        }

        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        $old = \App\Models\OrgStructureDocument::latest()->first();
        if ($old) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($old->path);
            $old->delete();
        }

        $file = $request->file('file');
        $name = time() . '_' . \Illuminate\Support\Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('org_structure', $name, 'public');

        \App\Models\OrgStructureDocument::create([
            'nama_file'   => $file->getClientOriginalName(),
            'path'        => $path,
            'mime_type'   => $file->getMimeType(),
            'size'        => $file->getSize(),
            'uploaded_by' => auth()->id(),
        ]);

        ActivityLog::record('upload', 'Struktur Organisasi', null, "Upload dokumen struktur organisasi: {$file->getClientOriginalName()}");

        return back()->with('success', 'Dokumen struktur organisasi berhasil diupload.');
    }

    public function previewOrgDocument()
    {
        $doc = \App\Models\OrgStructureDocument::latest()->first();
        if (!$doc || !\Illuminate\Support\Facades\Storage::disk('public')->exists($doc->path)) {
            abort(404, 'Dokumen tidak ditemukan.');
        }

        $fullPath = \Illuminate\Support\Facades\Storage::disk('public')->path($doc->path);
        return response()->file($fullPath, [
            'Content-Type'        => $doc->mime_type,
            'Content-Disposition' => 'inline; filename="' . $doc->nama_file . '"',
        ]);
    }

    public function destroyOrgDocument()
    {
        if (!$this->isAdminSettings()) {
            abort(403, 'Kamu tidak memiliki akses untuk menghapus dokumen ini.');
        }

        $doc = \App\Models\OrgStructureDocument::latest()->first();
        if ($doc) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($doc->path);
            $nama = $doc->nama_file;
            $doc->delete();
            ActivityLog::record('delete', 'Struktur Organisasi', null, "Hapus dokumen struktur organisasi: {$nama}");
        }

        return back()->with('success', 'Dokumen struktur organisasi berhasil dihapus.');
    }

    public function updateAtasan(Request $request, Employee $employee)
    {
        if (!$this->isAdminSettings()) {
            abort(403, 'Kamu tidak memiliki akses untuk mengubah struktur organisasi.');
        }

        $data = $request->validate([
            'atasan_id' => 'nullable|exists:employees,id',
        ]);
        $atasanId = $data['atasan_id'] ?: null;

        if ($atasanId && (int) $atasanId === $employee->id) {
            return back()->withErrors(['atasan_id' => 'Karyawan tidak bisa menjadi atasan untuk dirinya sendiri.']);
        }

        // Cegah struktur melingkar: telusuri ke atas dari atasan yang dipilih — kalau ketemu
        // balik ke karyawan ini sendiri, berarti dia salah satu bawahan (langsung/tidak
        // langsung) dari atasan barunya itu, dan itu akan bikin rantai atasan-bawahan berputar.
        if ($atasanId) {
            $cursorId = $atasanId;
            $guard = 0;
            while ($cursorId && $guard < 100) {
                if ((int) $cursorId === $employee->id) {
                    return back()->withErrors(['atasan_id' => 'Tidak bisa memilih bawahan sendiri sebagai atasan — akan membuat struktur organisasi melingkar.']);
                }
                $cursorId = Employee::where('id', $cursorId)->value('atasan_id');
                $guard++;
            }
        }

        $namaLama = $employee->atasan?->nama_lengkap ?? '— (tidak ada) —';
        $employee->update(['atasan_id' => $atasanId]);
        $namaBaru = $employee->fresh()->atasan?->nama_lengkap ?? '— (tidak ada) —';

        ActivityLog::record('update', 'Struktur Organisasi', $employee->nama_lengkap, "Atasan diubah dari \"{$namaLama}\" menjadi \"{$namaBaru}\"");

        return back()->with('success', "Atasan {$employee->nama_lengkap} berhasil diperbarui.");
    }
}
