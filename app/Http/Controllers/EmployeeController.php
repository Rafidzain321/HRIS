<?php
// app>Http>Controllers>EmployeeController.php
namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\Position;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $today   = Carbon::today();
        $pid     = $this->activeProjectId();
        $search  = $request->get('search', '');
        $jabatan = $request->get('jabatan', '');

        // Fungsi apply filter — dipakai di $query dan $freshQuery supaya konsisten
        $applyFilters = function ($q) use ($pid, $search, $jabatan) {
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
            return $q;
        };

        // Query utama untuk paginate
        $query = $applyFilters(Employee::aktif()->with(['position', 'documents', 'project']))
            ->orderBy('nama_lengkap');

        // Highlight: cari halaman yang mengandung employee id
        $highlight = $request->get('highlight');
        if ($highlight && !$request->get('page')) {
            $allIds = $applyFilters(Employee::aktif())
                ->orderBy('nama_lengkap')
                ->pluck('id')
                ->toArray();

            $pos = array_search((int) $highlight, $allIds);

            if ($pos !== false) {
                $targetPage = (int) floor($pos / 50) + 1;
                $request->merge(['page' => $targetPage]);
            }
        }

        $employees = $query->paginate(50)->appends($request->except('highlight'))->through(fn($e) => [
            'id' => $e->id,
            'no_ktp' => $e->no_ktp,
            'id_badge' => $e->id_badge,
            'nama_lengkap' => $e->nama_lengkap,
            'jabatan' => $e->position?->nama_jabatan ?? '-',
            'alamat' => $e->alamat,
            'agama' => $e->agama,
            'type_sim' => $e->type_sim,
            'sim_status' => $e->sim_status,
            'sio_k3' => $e->sio_k3,
            'mcu_status' => $e->mcu_status,
            'status_mcu' => $e->status_mcu,
            'status_kp' => $e->status_kp,
            'badge_status' => $e->badge_status,
            'badge_days' => $e->expire_badge ? (int) now()->diffInDays($e->expire_badge, false) : null,
            'expired_sim' => $e->expired_sim?->format('d M Y'),
            'exp_mcu' => $e->exp_mcu?->format('d M Y'),
            'expire_badge' => $e->expire_badge?->format('d M Y'),
            'tanggal_masuk' => $e->tanggal_masuk?->format('d M Y'),
            'docs' => $e->documents->pluck('tipe')->unique()->values(),
            'umur' => $e->tanggal_lahir ? (int) $e->tanggal_lahir->age : null,
            'project_id'   => $e->project_id,
            'project_nama' => $e->project?->nama ?? '-',
        ]);

        $terminated = Employee::with('position')
            ->where('status', 'NONAKTIF')
            ->whereNotNull('tanggal_keluar')
            ->when($pid, fn($q) => $q->where('project_id', $pid))
            ->orderByDesc('tanggal_keluar')
            ->get()
            ->map(fn($e) => [
                'id' => $e->id,
                'id_badge' => $e->id_badge,
                'no_ktp' => $e->no_ktp, 
                'nama_lengkap' => $e->nama_lengkap,
                'jabatan' => $e->position?->nama_jabatan ?? '-',
                'tanggal_keluar' => $e->tanggal_keluar?->format('Y-m-d'),
                'alasan_keluar' => $e->alasan_keluar,
                'catatan_keluar' => $e->catatan_keluar,
            ]);

        $base = Employee::aktif()->when($pid, fn($q) => $q->where('project_id', $pid));
        $stats = [
            'total' => (clone $base)->count(),
            'dump_truck' => (clone $base)->whereHas('position', fn($q) => $q->where('nama_jabatan', 'Driver Dump Truck'))->count(),
            'spotter' => (clone $base)->whereHas('position', fn($q) => $q->where('nama_jabatan', 'Spotter'))->count(),
            'pmcow' => (clone $base)->whereHas('position', fn($q) => $q->where('nama_jabatan', 'PMCOW'))->count(),
            'hes' => (clone $base)->whereHas('position', fn($q) => $q->where('nama_jabatan', 'HES Man'))->count(),
        ];

        $jabatan_list = Position::orderBy('nama_jabatan')->get(['id', 'nama_jabatan']);
        return Inertia::render('Employee/Index', compact('employees', 'terminated', 'jabatan_list', 'stats'));
    }

    public function store(Request $request)
    {
        if ($this->isViewer()) {
            return back()->with('error', 'Viewer tidak memiliki akses untuk mengubah data.');
        }

        $data = $request->validate([
            'nama_lengkap' => 'required|string|max:200',
            'nama_ibu' => 'nullable|string|max:200',
            'id_badge' => [
                'required', 'string', 'max:30',
                \Illuminate\Validation\Rule::unique('employees', 'id_badge')
                    ->where('project_id', $request->input('project_id') ?? auth()->user()->project_id)
                    ->where('status', 'AKTIF'),
            ],
            'no_ktp' => [
                'nullable', 'string', 'max:20',
                \Illuminate\Validation\Rule::unique('employees', 'no_ktp')
                    ->where('project_id', $request->input('project_id') ?? auth()->user()->project_id),
            ],
            'no_telepon' => 'nullable|string|max:25',
            'tempat_lahir' => 'nullable|string|max:100',
            'tanggal_lahir' => 'nullable|date',
            'position_id' => 'nullable|exists:positions,id',
            'ptkp' => 'nullable|in:TK/0,TK/1,TK/2,TK/3,K/0,K/1,K/2,K/3',
            'status' => 'required|in:AKTIF,NONAKTIF',
            'status_mcu' => 'nullable|string|max:20',
            'lokasi_mcu' => 'nullable|string|max:100',
            'exp_mcu' => 'nullable|date',
        ], [
            'nama_lengkap.required' => 'Nama lengkap wajib diisi.',
            'id_badge.required'     => 'ID Badge wajib diisi.',
            'id_badge.unique'       => 'ID Badge ini sudah digunakan oleh karyawan lain di project ini.',
            'no_ktp.unique'         => 'No. KTP ini sudah terdaftar di project ini.',
            'status.required'       => 'Status wajib dipilih.',
        ]);

        $user = auth()->user();
        if (!$user->hasRole('super-admin')) {
            $data['project_id'] = $user->project_id;
        } else {
            $data['project_id'] = $request->input('project_id');
        }

        $employee = Employee::create($data);

        ActivityLog::record('create', 'Karyawan', $data['nama_lengkap'],
            "Tambah karyawan baru: {$data['nama_lengkap']} ({$data['id_badge']})"
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

        $umur = $employee->tanggal_lahir
            ? \Carbon\Carbon::parse($employee->tanggal_lahir)->age
            : null;

        return Inertia::render('Employee/Edit', [
            'employee' => array_merge($employee->toArray(), [
                'tanggal_lahir' => $employee->tanggal_lahir?->format('Y-m-d'),
                'tanggal_masuk' => $employee->tanggal_masuk?->format('Y-m-d'),
                'tanggal_hi' => $employee->tanggal_hi?->format('Y-m-d'),
                'expire_badge' => $employee->expire_badge?->format('Y-m-d'),
                'exp_kp' => $employee->exp_kp?->format('Y-m-d'),
                'expired_sim' => $employee->expired_sim?->format('Y-m-d'),
                'expire_sio' => $employee->expire_sio?->format('Y-m-d'),
                'exp_mcu' => $employee->exp_mcu?->format('Y-m-d'),
                'start_pkwt' => $employee->start_pkwt?->format('Y-m-d'),
                'end_pkwt' => $employee->end_pkwt?->format('Y-m-d'),
                'umur' => $umur,
            ]),
            'positions' => \App\Models\Position::orderBy('nama_jabatan')->get(['id', 'nama_jabatan']),
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

        $data = $request->validate([
            'nama_lengkap' => 'required|string|max:200',
            'nama_ibu' => 'nullable|string|max:200',
            'id_badge' => [
                'required', 'string', 'max:30',
                \Illuminate\Validation\Rule::unique('employees', 'id_badge')
                    ->ignore($employee->id)
                    ->where('project_id', $employee->project_id)
                    ->where('status', 'AKTIF'),
            ],
            'no_ktp' => [
                'nullable', 'string', 'max:20',
                \Illuminate\Validation\Rule::unique('employees', 'no_ktp')
                    ->ignore($employee->id)->whereNotNull('no_ktp')->where('project_id', $employee->project_id),
            ],
            'no_telepon' => 'nullable|string|max:25',
            'tempat_lahir' => 'nullable|string|max:100',
            'tanggal_lahir' => 'nullable|date',
            'alamat' => 'nullable|string',
            'kota_asal' => 'nullable|string|max:100',
            'tamatan' => 'nullable|string|max:20',
            'ptkp' => 'nullable|in:TK/0,TK/1,TK/2,TK/3,K/0,K/1,K/2,K/3',
            'ccpm' => 'nullable|string|max:30',
            'expire_badge' => 'nullable|date',
            'status_kp' => 'nullable|string|max:100',
            'kp_ready' => 'nullable|string|max:100',
            'exp_kp' => 'nullable|date',
            'type_sim' => 'nullable|string|max:10',
            'no_sim' => 'nullable|string|max:30',
            'expired_sim' => 'nullable|date',
            'sio_k3' => 'nullable|in:YES,NO',
            'no_sio' => 'nullable|string|max:50',
            'rfid' => 'nullable|string|max:50',
            'expire_sio' => 'nullable|date',
            'nama_perusahaan_sio' => 'nullable|string|max:200',
            'tipe_sio' => 'nullable|string|max:100',
            'exp_mcu' => 'nullable|date',
            'status_mcu' => 'nullable|string|max:20',
            'lokasi_mcu' => 'nullable|string|max:100',
            'ukuran_baju' => 'nullable|string|max:10',
            'ukuran_sepatu' => 'nullable|string|max:10',
            'start_pkwt' => 'nullable|date',
            'end_pkwt' => 'nullable|date',
            'position_id' => 'nullable|exists:positions,id',
            'tanggal_masuk' => 'nullable|date',
            'agama' => 'nullable|string|max:50',
            'tgl_mcu' => 'nullable|date',
            'derajat_kesehatan' => 'nullable|string|max:20',
            'sim_kota_keluar' => 'nullable|string|max:100',
            'no_bpjs' => 'nullable|string|max:50',
            'no_contract' => 'nullable|string|max:100',
            'no_rekening'   => 'nullable|string|max:50',
            'no_bpjs_tk'    => 'nullable|string|max:20',
            'no_bpjs_kes'   => 'nullable|string|max:20',
            'nama_bank'     => 'nullable|string|max:50',
        ]);

        // Deteksi field yang berubah untuk log
        $changed = [];
        $watchFields = [
            'no_rekening' => 'No. Rekening',
            'expired_sim' => 'Expired SIM',
            'exp_mcu'     => 'Expired MCU',
            'expire_badge'=> 'Expired Badge',
            'position_id' => 'Jabatan',
            'ptkp'        => 'PTKP',
            'status_mcu'  => 'Status MCU',
        ];
        foreach ($watchFields as $field => $label) {
            $old = $employee->$field instanceof \Carbon\Carbon
                ? $employee->$field->format('Y-m-d')
                : $employee->$field;
            $new = $data[$field] ?? null;
            if ((string)$old !== (string)$new) {
                $changed[] = "{$label}: {$old} -> {$new}";
            }
        }

        $employee->update($data);

        $desc = "Update data karyawan: {$employee->nama_lengkap} ({$employee->id_badge})";
        if (!empty($changed)) {
            $desc .= ' | ' . implode(', ', $changed);
        }
        ActivityLog::record('update', 'Karyawan', $employee->nama_lengkap, $desc);

        return redirect()->route('employees.index')
            ->with('success', "Data {$employee->nama_lengkap} berhasil diperbarui.");
    }

    public function terminate(Request $request, Employee $employee)
    {
        if ($this->isViewer()) {
            return back()->with('error', 'Viewer tidak memiliki akses untuk mengubah data.');
        }

        $data = $request->validate([
            'tanggal_keluar' => 'required|date',
            'alasan_keluar' => 'required|in:RESIGN,PHK,KONTRAK HABIS,MENINGGAL DUNIA,MUTASI,LAINNYA',
            'catatan_keluar' => 'nullable|string|max:500',
        ]);

        $employee->update([
            'status' => 'NONAKTIF',
            'tanggal_keluar' => $data['tanggal_keluar'],
            'alasan_keluar' => $data['alasan_keluar'],
            'catatan_keluar' => $data['catatan_keluar'] ?? null,
        ]);

        \App\Models\EmployeeTerminationLog::create([
            'employee_id' => $employee->id,
            'tanggal_keluar' => $data['tanggal_keluar'],
            'alasan_keluar' => $data['alasan_keluar'],
            'catatan_keluar' => $data['catatan_keluar'] ?? null,
            'dicatat_oleh' => auth()->user()->name ?? 'System',
        ]);

        ActivityLog::record('delete', 'Karyawan', $employee->nama_lengkap,
            "Terminate {$employee->nama_lengkap} ({$employee->id_badge}): {$data['alasan_keluar']} tgl {$data['tanggal_keluar']}"
        );

        $tglKeluar = \Carbon\Carbon::parse($data['tanggal_keluar']);
        $bulanSekarang = \Carbon\Carbon::today()->format('Y-m');
        $bulanKeluar = $tglKeluar->format('Y-m');

        if ($bulanKeluar < $bulanSekarang) {
            \App\Models\TimesheetMember::where('id_badge', $employee->id_badge)
                ->update(['aktif' => false]);
        }

        return redirect()->route('employees.index')
            ->with('success', "{$employee->nama_lengkap} berhasil di-terminate ({$data['alasan_keluar']}).");
    }

    public function checkBadge(Request $request)
    {
        $badge = $request->get('badge');
        $exclude = $request->get('exclude');
        $pid = $this->activeProjectId();

        $exists = Employee::where('id_badge', $badge)
            ->when($exclude, fn($q) => $q->where('id', '!=', $exclude))
            ->when($pid, fn($q) => $q->where('project_id', $pid))
            ->first();

        return response()->json([
            'exists' => (bool) $exists,
            'nama' => $exists?->nama_lengkap,
        ]);
    }

    public function reactivate(Employee $employee)
    {
        if ($this->isViewer()) {
            return back()->with('error', 'Viewer tidak memiliki akses untuk mengubah data.');
        }

        $employee->update([
            'status' => 'AKTIF',
            'tanggal_keluar' => null,
            'alasan_keluar' => null,
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

        $hasTimesheet = \App\Models\Timesheet::where('employee_id', $employee->id)->exists();
        $hasPayroll = \App\Models\EmployeePayroll::where('employee_id', $employee->id)->exists();

        if ($hasTimesheet || $hasPayroll) {
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