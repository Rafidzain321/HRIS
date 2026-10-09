<?php
namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ClientProject;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeHoDetail;
use App\Models\EmployeePayroll;
use App\Models\EmployeeSalaryHistory;
use App\Models\EmployeeTerminationLog;
use App\Models\Position;
use App\Models\Project;
use App\Models\Timesheet;
use App\Models\TimesheetMember;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class EmployeeController extends Controller
{
    const PTKP_RULE = 'nullable|in:TK/0,TK/1,TK/2,TK/3,K/0,K/1,K/2,K/3';

    const HO_DETAIL_RULES = [
        'ho_detail'                 => 'nullable|array',
        'ho_detail.unit'            => 'nullable|in:HO-1,HO-2',
        'ho_detail.ho_division_id'  => 'nullable|exists:ho_divisions,id',
        'ho_detail.nik_ho'          => 'nullable|string|max:40',
        'ho_detail.lokasi_kerja'    => 'nullable|string|max:255',
        'ho_detail.status_karyawan' => 'nullable|string|max:40',
        'ho_detail.nama_ktp'        => 'nullable|string|max:255',
        'ho_detail.no_kk'           => 'nullable|string|max:40',
        'ho_detail.rt_rw'           => 'nullable|string|max:20',
        'ho_detail.kelurahan'       => 'nullable|string|max:255',
        'ho_detail.kecamatan'       => 'nullable|string|max:255',
        'ho_detail.propinsi'        => 'nullable|string|max:255',
        'ho_detail.npwp'            => 'nullable|string|max:40',
        'ho_detail.email'           => 'nullable|string|max:255',
    ];

    const CLIENT_PROJECT_RULES = [
        'client_project_ids'   => 'nullable|array',
        'client_project_ids.*' => 'exists:client_projects,id',
    ];

    // Kartu statistik jabatan lapangan di Data Karyawan (kantor non-HO).
    const STAT_JABATAN = [
        'dump_truck' => 'Driver Dump Truck',
        'spotter'    => 'Spotter',
        'pmcow'      => 'PMCOW',
        'hes'        => 'HES Man',
    ];

    const TANGGAL_EDIT = [
        'tanggal_lahir', 'tanggal_masuk', 'tanggal_akhir_probation', 'tanggal_hi', 'expire_badge',
        'exp_kp', 'expired_sim', 'expire_sio', 'tgl_mcu', 'exp_mcu', 'start_pkwt', 'end_pkwt', 'tanggal_keluar',
    ];

    public function index(Request $request)
    {
        $pid     = $this->activeProjectId();
        $search  = $request->get('search', '');
        $jabatan = $request->get('jabatan', '');
        $clientProjectId = $request->get('client_project', '');

        // Dipakai untuk query tabel & pencarian halaman "highlight" supaya hasilnya konsisten.
        $applyFilters = function ($q) use ($pid, $search, $jabatan, $clientProjectId) {
            $q->when($pid, fn($q) => $q->where('project_id', $pid));
            if ($search) {
                $q->where(fn($q2) => $q2
                    ->where('nama_lengkap', 'like', "%$search%")
                    ->orWhere('no_ktp', 'like', "%$search%")
                    ->orWhere('id_badge', 'like', "%$search%")
                    ->orWhereHas('position', fn($q3) => $q3->where('nama_jabatan', 'like', "%$search%"))
                );
            }
            if ($jabatan) {
                $q->whereHas('position', fn($q2) => $q2->where('nama_jabatan', $jabatan));
            }
            if ($clientProjectId) {
                $q->whereHas('clientProjects', fn($q2) => $q2->where('client_projects.id', $clientProjectId));
            }
            return $q;
        };

        $projectInfo = $pid ? Project::find($pid, ['id', 'kode', 'nama', 'tipe_gaji']) : null;

        $query = $applyFilters(Employee::aktif()->with(['position', 'documents', 'project', 'hoDetail', 'clientProjects']))
            ->orderBy('nama_lengkap');

        // ?highlight=id (mis. dari notifikasi): lompat ke halaman yang memuat karyawan tsb.
        $highlight = $request->get('highlight');
        if ($highlight && !$request->get('page')) {
            $allIds = $applyFilters(Employee::aktif())->orderBy('nama_lengkap')->pluck('id')->toArray();
            $pos = array_search((int) $highlight, $allIds);
            if ($pos !== false) {
                $request->merge(['page' => (int) floor($pos / 50) + 1]);
            }
        }

        $employees = $query->paginate(50)->appends($request->except('highlight'))->through(fn($e) => [
            'id'                 => $e->id,
            'no_ktp'             => $e->no_ktp,
            'id_badge'           => $e->id_badge,
            'nama_lengkap'       => $e->nama_lengkap,
            'jabatan'            => $e->position?->nama_jabatan ?? '-',
            'alamat'             => $e->alamat,
            'agama'              => $e->agama,
            'type_sim'           => $e->type_sim,
            'sim_status'         => $e->sim_status,
            'sio_k3'             => $e->sio_k3,
            'mcu_status'         => $e->mcu_status,
            'status_mcu'         => $e->status_mcu,
            'status_kp'          => $e->status_kp,
            'badge_status'       => $e->badge_status,
            'badge_days'         => $e->expire_badge ? (int) now()->diffInDays($e->expire_badge, false) : null,
            'expired_sim'        => $e->expired_sim?->format('d M Y'),
            'exp_mcu'            => $e->exp_mcu?->format('d M Y'),
            'expire_badge'       => $e->expire_badge?->format('d M Y'),
            'tanggal_masuk'      => $e->tanggal_masuk?->format('d M Y'),
            'docs'               => $e->documents->pluck('tipe')->unique()->values(),
            'umur'               => $e->tanggal_lahir ? (int) $e->tanggal_lahir->age : null,
            'masa_kerja'         => $e->tanggal_masuk ? $this->formatMasaKerja($e->tanggal_masuk) : null,
            'project_id'         => $e->project_id,
            'project_nama'       => $e->project?->nama ?? '-',
            'client_projects'    => $e->clientProjects->pluck('kode')->values(),
            'ho_unit'            => $e->hoDetail?->unit,
            'ho_nik'             => $e->hoDetail?->nik_ho,
            'ho_lokasi_kerja'    => $e->hoDetail?->lokasi_kerja,
            'ho_status_karyawan' => $e->hoDetail?->status_karyawan,
            'ho_no_kk'           => $e->hoDetail?->no_kk,
            'ho_rt_rw'           => $e->hoDetail?->rt_rw,
            'ho_kelurahan'       => $e->hoDetail?->kelurahan,
            'ho_kecamatan'       => $e->hoDetail?->kecamatan,
            'ho_propinsi'        => $e->hoDetail?->propinsi,
            'ho_npwp'            => $e->hoDetail?->npwp,
            'ho_email'           => $e->hoDetail?->email,
        ]);

        $terminated = Employee::with('position')
            ->where('status', 'NONAKTIF')
            ->whereNotNull('tanggal_keluar')
            ->when($pid, fn($q) => $q->where('project_id', $pid))
            ->orderByDesc('tanggal_keluar')
            ->get()
            ->map(fn($e) => [
                'id'             => $e->id,
                'id_badge'       => $e->id_badge,
                'no_ktp'         => $e->no_ktp,
                'nama_lengkap'   => $e->nama_lengkap,
                'jabatan'        => $e->position?->nama_jabatan ?? '-',
                'tanggal_keluar' => $e->tanggal_keluar?->format('Y-m-d'),
                'alasan_keluar'  => $e->alasan_keluar,
                'catatan_keluar' => $e->catatan_keluar,
            ]);

        $base  = Employee::aktif()->when($pid, fn($q) => $q->where('project_id', $pid));
        $stats = ['total' => (clone $base)->count()];
        foreach (self::STAT_JABATAN as $key => $namaJabatan) {
            $stats[$key] = (clone $base)->whereHas('position', fn($q) => $q->where('nama_jabatan', $namaJabatan))->count();
        }

        // HO tidak punya jabatan lapangan (dump truck/spotter/dll) — hitung total & jabatan
        // terbanyak per unit (Semua/HO-1/HO-2) dari data aslinya, bukan hardcode.
        if ($projectInfo && $projectInfo->tipe_gaji === 'ho') {
            $hoEmployees = (clone $base)->with(['position', 'hoDetail'])->get();
            $buildUnitStats = fn($collection) => [
                'total'       => $collection->count(),
                'top_jabatan' => $collection
                    ->filter(fn($e) => $e->position?->nama_jabatan)
                    ->groupBy(fn($e) => $e->position->nama_jabatan)
                    ->map(fn($g, $label) => ['label' => $label, 'val' => $g->count()])
                    ->sortByDesc('val')
                    ->take(4)
                    ->values(),
            ];
            $stats['ho'] = [
                'all'  => $buildUnitStats($hoEmployees),
                'HO-1' => $buildUnitStats($hoEmployees->filter(fn($e) => $e->hoDetail?->unit === 'HO-1')),
                'HO-2' => $buildUnitStats($hoEmployees->filter(fn($e) => $e->hoDetail?->unit === 'HO-2')),
            ];
        }

        // Dropdown filter: project milik kantor yang sedang dibuka (diatur di Pengaturan > Data Project),
        // plus project lain yang kebetulan dipegang karyawan kantor ini.
        $clientProjectList = ClientProject::orderBy('kode')
            ->withCount(['employees' => fn($q) => $q->where('status', 'AKTIF')->when($pid, fn($q2) => $q2->where('project_id', $pid))])
            ->when($pid, fn($q) => $q->withExists(['kantors as milik_kantor' => fn($q2) => $q2->where('projects.id', $pid)]))
            ->get(['id', 'kode', 'is_active'])
            ->filter(fn($cp) => !$pid || $cp->milik_kantor || $cp->employees_count > 0 || (string) $cp->id === (string) $clientProjectId)
            ->map(fn($cp) => ['id' => $cp->id, 'kode' => $cp->kode, 'jumlah' => $cp->employees_count, 'is_active' => $cp->is_active])
            ->values();

        return Inertia::render('Employee/Index', [
            'employees'           => $employees,
            'terminated'          => $terminated,
            'jabatan_list'        => Position::orderBy('nama_jabatan')->get(['id', 'nama_jabatan']),
            'client_project_list' => $clientProjectList,
            'stats'               => $stats,
            'project_info'        => $projectInfo,
        ]);
    }

    private function formatMasaKerja($tanggalMasuk): string
    {
        $diff = $tanggalMasuk->diff(now());
        return ($diff->y > 0 ? "{$diff->y} thn " : '') . "{$diff->m} bln";
    }

    // HO tidak pakai id_badge sama sekali — tidak wajib & tidak unik. Kantor lapangan: wajib & unik
    // di antara karyawan AKTIF kantor yang sama.
    private function idBadgeRules(bool $isHo, $projectId, ?int $ignoreId = null): array
    {
        if ($isHo) return ['nullable', 'string', 'max:30'];
        return [
            'required', 'string', 'max:30',
            Rule::unique('employees', 'id_badge')->ignore($ignoreId)->where('project_id', $projectId)->where('status', 'AKTIF'),
        ];
    }

    public function store(Request $request)
    {
        if ($this->isViewer()) {
            return back()->with('error', 'Viewer tidak memiliki akses untuk mengubah data.');
        }

        $user = auth()->user();
        $targetProjectId = $user->hasRole('super-admin') ? $request->input('project_id') : $user->project_id;
        $isHoProject = $targetProjectId && Project::find($targetProjectId)?->tipe_gaji === 'ho';

        $data = $request->validate([
            'nama_lengkap'            => 'required|string|max:200',
            'nama_ibu'                => 'nullable|string|max:200',
            'id_badge'                => $this->idBadgeRules($isHoProject, $targetProjectId),
            'no_ktp'                  => ['nullable', 'string', 'max:20', Rule::unique('employees', 'no_ktp')->where('project_id', $targetProjectId)],
            'no_telepon'              => 'nullable|string|max:25',
            'tempat_lahir'            => 'nullable|string|max:100',
            'tanggal_lahir'           => 'nullable|date',
            'tanggal_masuk'           => 'nullable|date',
            'tanggal_akhir_probation' => 'nullable|date',
            'alamat'                  => 'nullable|string',
            'agama'                   => 'nullable|string|max:50',
            'position_id'             => 'nullable|exists:positions,id',
            'department_id'           => 'nullable|exists:departments,id',
            'ptkp'                    => self::PTKP_RULE,
            'status'                  => 'required|in:AKTIF,NONAKTIF',
            'status_mcu'              => 'nullable|string|max:20',
            'lokasi_mcu'              => 'nullable|string|max:100',
            'exp_mcu'                 => 'nullable|date',
            ...self::CLIENT_PROJECT_RULES,
            ...self::HO_DETAIL_RULES,
        ], [
            'nama_lengkap.required' => 'Nama lengkap wajib diisi.',
            'id_badge.required'     => 'ID Badge wajib diisi.',
            'id_badge.unique'       => 'ID Badge ini sudah digunakan oleh karyawan lain di project ini.',
            'no_ktp.unique'         => 'No. KTP ini sudah terdaftar di project ini.',
            'status.required'       => 'Status wajib dipilih.',
        ]);

        $hoDetailData     = $data['ho_detail'] ?? null;
        $clientProjectIds = $data['client_project_ids'] ?? [];
        unset($data['ho_detail'], $data['client_project_ids']);
        $data['project_id'] = $targetProjectId;

        $employee = Employee::create($data);
        $employee->clientProjects()->sync($clientProjectIds);

        if ($isHoProject && $hoDetailData && array_filter($hoDetailData, fn($v) => $v !== null)) {
            EmployeeHoDetail::updateOrCreate(['employee_id' => $employee->id], $hoDetailData);
        }

        ActivityLog::record('create', 'Karyawan', $data['nama_lengkap'],
            "Tambah karyawan baru: {$data['nama_lengkap']} (" . ($data['id_badge'] ?? '-') . ")"
        );

        return redirect()->route('employees.index')
            ->with('success', "Karyawan {$data['nama_lengkap']} berhasil ditambahkan.");
    }

    public function edit(Employee $employee)
    {
        if ($this->isViewer()) {
            return redirect()->route('employees.index')
                ->with('error', 'Viewer tidak memiliki akses halaman edit.');
        }

        $employee->loadMissing('hoDetail', 'project', 'clientProjects');
        $projectInfo = $employee->project
            ? Project::find($employee->project_id, ['id', 'kode', 'nama', 'tipe_gaji'])
            : null;

        $tanggal = [];
        foreach (self::TANGGAL_EDIT as $field) {
            $tanggal[$field] = $employee->$field?->format('Y-m-d');
        }

        return Inertia::render('Employee/Edit', [
            'employee' => array_merge($employee->toArray(), $tanggal, [
                'umur'               => $employee->tanggal_lahir?->age,
                'ho_detail'          => $employee->hoDetail,
                'client_project_ids' => $employee->clientProjects->pluck('id')->values(),
            ]),
            'positions'   => Position::orderBy('nama_jabatan')->get(['id', 'nama_jabatan']),
            'departments' => Department::where('is_active', true)->orderBy('nama')->get(['id', 'nama', 'kode']),
            // Divisi HO (Pengajuan Training): yang aktif + divisi karyawan ini walau sudah dinonaktifkan.
            'ho_divisions' => \App\Models\HoDivision::where(fn($q) => $q->where('is_active', true)->orWhere('id', $employee->hoDetail?->ho_division_id))->orderBy('nama')->get(['id', 'nama']),
            // Pilihan = project AKTIF milik kantor karyawan ini, plus project yang sudah terpasang
            // (walau sudah nonaktif/pindah kantor) supaya tidak hilang diam-diam saat disimpan.
            'client_project_list' => ClientProject::orderBy('kode')
                ->where(fn($q) => $q
                    ->where(fn($q2) => $q2->whereHas('kantors', fn($q3) => $q3->where('projects.id', $employee->project_id))->where('is_active', true))
                    ->orWhereIn('id', $employee->clientProjects->pluck('id')))
                ->get(['id', 'kode', 'is_active']),
            'can_manage_client_project' => $this->isAdminSettings(),
            'project_info'              => $projectInfo,
            // Riwayat gaji mentah hasil import HO — arsip referensi, cuma relevan untuk karyawan HO.
            // Data gaji sensitif, jadi cuma dikirim ke frontend kalau yang buka super-admin —
            // akun lain (termasuk atasan/manager) tidak boleh lihat sama sekali.
            'salary_history' => auth()->user()?->hasRole('super-admin')
                ? EmployeeSalaryHistory::where('employee_id', $employee->id)
                    ->orderByRaw('tahun IS NULL, tahun, bulan IS NULL, bulan, urutan')
                    ->get(['label', 'nominal', 'tahun', 'bulan'])
                : [],
        ]);
    }

    public function show(Employee $employee)
    {
        return $this->edit($employee);
    }

    public function update(Request $request, Employee $employee)
    {
        if ($this->isViewer()) {
            return back()->with('error', 'Viewer tidak memiliki akses untuk mengubah data.');
        }
        // Karyawan terminated hanya bisa dilihat — aktifkan kembali dulu lewat arsip kalau mau diubah.
        if ($employee->status === 'NONAKTIF') {
            return back()->with('error', 'Karyawan sudah terminated, data hanya bisa dilihat.');
        }

        $isHoProject = $employee->project?->tipe_gaji === 'ho';

        $data = $request->validate([
            'nama_lengkap'            => 'required|string|max:200',
            'nama_ibu'                => 'nullable|string|max:200',
            'id_badge'                => $this->idBadgeRules($isHoProject, $employee->project_id, $employee->id),
            ...self::HO_DETAIL_RULES,
            'no_ktp'                  => [
                'nullable', 'string', 'max:20',
                Rule::unique('employees', 'no_ktp')->ignore($employee->id)->whereNotNull('no_ktp')->where('project_id', $employee->project_id),
            ],
            'no_telepon'              => 'nullable|string|max:25',
            'tempat_lahir'            => 'nullable|string|max:100',
            'tanggal_lahir'           => 'nullable|date',
            'alamat'                  => 'nullable|string',
            'kota_asal'               => 'nullable|string|max:100',
            'tamatan'                 => 'nullable|string|max:20',
            'ptkp'                    => self::PTKP_RULE,
            'ccpm'                    => 'nullable|string|max:30',
            'expire_badge'            => 'nullable|date',
            'status_kp'               => 'nullable|string|max:100',
            'kp_ready'                => 'nullable|string|max:100',
            'exp_kp'                  => 'nullable|date',
            'type_sim'                => 'nullable|string|max:10',
            'no_sim'                  => 'nullable|string|max:30',
            'expired_sim'             => 'nullable|date',
            'sio_k3'                  => 'nullable|in:YES,NO',
            'no_sio'                  => 'nullable|string|max:50',
            'rfid'                    => 'nullable|string|max:50',
            'expire_sio'              => 'nullable|date',
            'nama_perusahaan_sio'     => 'nullable|string|max:200',
            'tipe_sio'                => 'nullable|string|max:100',
            'exp_mcu'                 => 'nullable|date',
            'status_mcu'              => 'nullable|string|max:20',
            'lokasi_mcu'              => 'nullable|string|max:100',
            'ukuran_baju'             => 'nullable|string|max:10',
            'ukuran_sepatu'           => 'nullable|string|max:10',
            'start_pkwt'              => 'nullable|date',
            'end_pkwt'                => 'nullable|date',
            'position_id'             => 'nullable|exists:positions,id',
            'department_id'           => 'nullable|exists:departments,id',
            'tanggal_masuk'           => 'nullable|date',
            'status_kerja'            => 'nullable|in:PKWT,PKWTT,PROBATION',
            'tanggal_akhir_probation' => 'nullable|date',
            'agama'                   => 'nullable|string|max:50',
            'tgl_mcu'                 => 'nullable|date',
            'derajat_kesehatan'       => 'nullable|string|max:20',
            'sim_kota_keluar'         => 'nullable|string|max:100',
            'no_bpjs'                 => 'nullable|string|max:50',
            'no_contract'             => 'nullable|string|max:100',
            'no_rekening'             => 'nullable|string|max:50',
            'no_bpjs_tk'              => 'nullable|string|max:20',
            'no_bpjs_kes'             => 'nullable|string|max:20',
            'nama_bank'               => 'nullable|string|max:50',
            ...self::CLIENT_PROJECT_RULES,
        ]);

        // Field penting yang perubahannya dicatat detail di log aktivitas.
        $watchFields = [
            'no_rekening'  => 'No. Rekening',
            'expired_sim'  => 'Expired SIM',
            'exp_mcu'      => 'Expired MCU',
            'expire_badge' => 'Expired Badge',
            'position_id'  => 'Jabatan',
            'ptkp'         => 'PTKP',
            'status_mcu'   => 'Status MCU',
        ];
        $changed = [];
        foreach ($watchFields as $field => $label) {
            $old = $employee->$field instanceof Carbon ? $employee->$field->format('Y-m-d') : $employee->$field;
            $new = $data[$field] ?? null;
            if ((string) $old !== (string) $new) {
                $changed[] = "{$label}: {$old} -> {$new}";
            }
        }

        $hoDetailData     = $data['ho_detail'] ?? null;
        $clientProjectIds = $data['client_project_ids'] ?? null;
        unset($data['ho_detail'], $data['client_project_ids']);

        if ($isHoProject) {
            $data['id_badge'] = $data['id_badge'] ?: null;
        }

        // Tanggal akhir probation cuma berlaku kalau statusnya Probation (HO: dari ho_detail.status_karyawan).
        $statusKerja = $isHoProject ? ($hoDetailData['status_karyawan'] ?? $employee->hoDetail?->status_karyawan) : ($data['status_kerja'] ?? null);
        if ($statusKerja !== 'PROBATION') {
            $data['tanggal_akhir_probation'] = null;
        }

        $employee->update($data);

        // null = field tidak dikirim -> project tidak diubah; [] = dikosongkan.
        if ($clientProjectIds !== null) {
            $employee->clientProjects()->sync($clientProjectIds);
        }

        if ($isHoProject && $hoDetailData) {
            EmployeeHoDetail::updateOrCreate(['employee_id' => $employee->id], $hoDetailData);
        }

        $desc = "Update data karyawan: {$employee->nama_lengkap} ({$employee->id_badge})";
        if ($changed) {
            $desc .= ' | ' . implode(', ', $changed);
        }
        ActivityLog::record('update', 'Karyawan', $employee->nama_lengkap, $desc);

        return redirect()->route('employees.index')
            ->with('success', "Data {$employee->nama_lengkap} berhasil diperbarui.");
    }

    public function pindahUnitHo(Request $request, Employee $employee)
    {
        if ($this->isViewer()) {
            return response()->json(['ok' => false, 'message' => 'Viewer tidak memiliki akses.'], 403);
        }

        if ($employee->project?->tipe_gaji !== 'ho') {
            return response()->json(['ok' => false, 'message' => 'Karyawan ini bukan karyawan Head Office.'], 422);
        }

        $data = $request->validate([
            'unit'    => 'required|in:HO-1,HO-2',
            'catatan' => 'nullable|string|max:500',
        ]);

        if ($employee->hoDetail?->unit === $data['unit']) {
            return response()->json([
                'ok'      => false,
                'message' => "Karyawan sudah berada di unit {$data['unit']}. Silakan refresh halaman.",
            ], 422);
        }

        EmployeeHoDetail::updateOrCreate(['employee_id' => $employee->id], ['unit' => $data['unit']]);

        $desc = "Pindah unit: {$employee->nama_lengkap} ke {$data['unit']}";
        if (!empty($data['catatan'])) {
            $desc .= " | Catatan: {$data['catatan']}";
        }
        ActivityLog::record('update', 'Pindah Unit HO', $employee->nama_lengkap, $desc);

        return response()->json([
            'ok'       => true,
            'message'  => "Karyawan berhasil dipindahkan ke unit {$data['unit']}.",
            'new_unit' => $data['unit'],
        ]);
    }

    public function terminate(Request $request, Employee $employee)
    {
        if ($this->isViewer()) {
            return back()->with('error', 'Viewer tidak memiliki akses untuk mengubah data.');
        }

        $data = $request->validate([
            'tanggal_keluar' => 'required|date',
            'alasan_keluar'  => 'required|in:RESIGN,PHK,KONTRAK HABIS,MENINGGAL DUNIA,MUTASI,LAINNYA',
            'catatan_keluar' => 'nullable|string|max:500',
        ]);
        $keluar = [
            'tanggal_keluar' => $data['tanggal_keluar'],
            'alasan_keluar'  => $data['alasan_keluar'],
            'catatan_keluar' => $data['catatan_keluar'] ?? null,
        ];

        $employee->update(['status' => 'NONAKTIF', ...$keluar]);
        EmployeeTerminationLog::create([
            'employee_id'  => $employee->id,
            ...$keluar,
            'dicatat_oleh' => auth()->user()->name ?? 'System',
        ]);

        ActivityLog::record('delete', 'Karyawan', $employee->nama_lengkap,
            "Terminate {$employee->nama_lengkap} ({$employee->id_badge}): {$data['alasan_keluar']} tgl {$data['tanggal_keluar']}"
        );

        // Keluar di bulan sebelumnya -> langsung hilang dari timesheet. Kalau keluar bulan ini,
        // tetap tampil supaya hari kerjanya bulan ini masih bisa diinput.
        if (Carbon::parse($data['tanggal_keluar'])->format('Y-m') < Carbon::today()->format('Y-m')) {
            TimesheetMember::where('id_badge', $employee->id_badge)->update(['aktif' => false]);
        }

        return redirect()->route('employees.index')
            ->with('success', "{$employee->nama_lengkap} berhasil di-terminate ({$data['alasan_keluar']}).");
    }

    public function checkBadge(Request $request)
    {
        $exclude = $request->get('exclude');
        $pid     = $this->activeProjectId();

        $exists = Employee::where('id_badge', $request->get('badge'))
            ->when($exclude, fn($q) => $q->where('id', '!=', $exclude))
            ->when($pid, fn($q) => $q->where('project_id', $pid))
            ->first();

        return response()->json([
            'exists' => (bool) $exists,
            'nama'   => $exists?->nama_lengkap,
        ]);
    }

    public function reactivate(Employee $employee)
    {
        if ($this->isViewer()) {
            return back()->with('error', 'Viewer tidak memiliki akses untuk mengubah data.');
        }

        $employee->update([
            'status'         => 'AKTIF',
            'tanggal_keluar' => null,
            'alasan_keluar'  => null,
            'catatan_keluar' => null,
        ]);

        ActivityLog::record('update', 'Karyawan', $employee->nama_lengkap,
            "Reaktivasi karyawan: {$employee->nama_lengkap} ({$employee->id_badge})"
        );

        return redirect()->route('employees.index')
            ->with('success', "{$employee->nama_lengkap} berhasil diaktifkan kembali.");
    }

    public function destroy(Employee $employee)
    {
        if ($this->isViewer()) {
            return back()->with('error', 'Viewer tidak memiliki akses untuk mengubah data.');
        }

        // Karyawan yang sudah punya riwayat timesheet/payroll tidak boleh dihapus permanen (pakai terminate).
        if (Timesheet::where('employee_id', $employee->id)->exists() || EmployeePayroll::where('employee_id', $employee->id)->exists()) {
            return redirect()->route('employees.index')
                ->with('error', "Data {$employee->nama_lengkap} tidak bisa dihapus permanen karena masih memiliki history timesheet/payroll.");
        }

        ActivityLog::record('delete', 'Karyawan', $employee->nama_lengkap,
            "Hapus permanen: {$employee->nama_lengkap} ({$employee->id_badge})"
        );

        $employee->delete();
        return redirect()->route('employees.index')->with('success', 'Karyawan berhasil dihapus.');
    }
}
