<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\TimesheetMember;
use Illuminate\Http\Request;

class TimesheetMemberController extends Controller
{
    public function index()
    {
        return TimesheetMember::with('employee.position')
            ->orderBy('urutan')
            ->orderBy('id_badge')
            ->get()
            ->map(fn($m) => [
                'id'            => $m->id,
                'id_badge'      => $m->id_badge,
                'nama_override' => $m->nama_override,
                'urutan'        => $m->urutan,
                'aktif'         => $m->aktif,
                'tipe'          => $m->tipe,
                'sub_group'     => $m->sub_group,
                'kelompok'      => $m->kelompok ?? 'per_jam',
                'nama_lengkap'  => $m->employee?->nama_lengkap ?? '—',
                'jabatan'       => $m->employee?->position?->nama_jabatan ?? '—',
            ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'id_badge'      => 'required|string|exists:employees,id_badge',
            'nama_override' => 'nullable|string|max:100',
            'urutan'        => 'nullable|integer',
            'sub_group'     => 'nullable|string|max:30',
            'kelompok'      => 'nullable|in:per_jam,flat',
        ]);

        $projectId = $this->activeProjectId();
        if (!$projectId && auth()->user()->hasRole('super-admin')) {
            $projectId = session('active_project_kode');
        }

        $exists = TimesheetMember::where('id_badge', $request->id_badge)
            ->when($projectId, fn($q) => $q->where('project_id', $projectId))
            ->exists();

        if ($exists) {
            return back()->with('error', 'Badge sudah ada di daftar timesheet project ini.');
        }

        $project = \App\Models\Project::find($projectId);
        $tipe    = $project?->tipe_timesheet === '8jam' ? '8jam' : '7jam';

        TimesheetMember::create([
            'id_badge'      => $request->id_badge,
            'nama_override' => $request->nama_override,
            'project_id'    => $projectId,
            'tipe'          => $tipe,
            'sub_group'     => $request->sub_group,
            'kelompok'      => $request->kelompok ?? 'per_jam',
            'urutan'        => $request->urutan
                ?? (TimesheetMember::when($projectId, fn($q) => $q->where('project_id', $projectId))->max('urutan') ?? 0) + 1,
            'aktif'         => true,
        ]);

        $nama = Employee::where('id_badge', $request->id_badge)->value('nama_lengkap') ?? $request->id_badge;
        ActivityLog::record('create', 'Timesheet', $nama, "Tambah anggota timesheet: {$nama} ({$request->id_badge})");

        return back()->with('success', 'Anggota berhasil ditambahkan.');
    }

    public function storeBulk(Request $request)
    {
        $request->validate([
            'badges'    => 'required|array|min:1',
            'badges.*'  => 'required|string',
            'sub_group' => 'nullable|string|max:30',
            'kelompok'  => 'nullable|in:per_jam,flat',
        ]);

        $projectId = $this->activeProjectId();
        if (!$projectId && auth()->user()->hasRole('super-admin')) {
            $projectId = session('active_project_kode');
        }

        $project  = \App\Models\Project::find($projectId);
        $tipe     = $project?->tipe_timesheet === '8jam' ? '8jam' : '7jam';
        $subGroup = $request->input('sub_group');
        $kelompok = $request->input('kelompok', 'per_jam');
        $badges   = $request->input('badges');
        $inserted = 0;
        $skipped  = 0;

        foreach ($badges as $badge) {
            $exists = TimesheetMember::where('id_badge', $badge)
                ->when($projectId, fn($q) => $q->where('project_id', $projectId))
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            $maxUrutan = TimesheetMember::when($projectId, fn($q) => $q->where('project_id', $projectId))
                ->max('urutan') ?? 0;

            TimesheetMember::create([
                'id_badge'   => $badge,
                'project_id' => $projectId,
                'tipe'       => $tipe,
                'sub_group'  => $subGroup,
                'kelompok'   => $kelompok,
                'aktif'      => true,
                'urutan'     => $maxUrutan + 1,
            ]);

            $inserted++;
        }

        $msg = "{$inserted} anggota berhasil ditambahkan";
        if ($skipped > 0) {
            $msg .= " ({$skipped} dilewati karena sudah ada)";
        }

        if ($inserted > 0) {
            ActivityLog::record('create', 'Timesheet', 'Tambah Massal', "{$inserted} anggota ditambahkan ke timesheet ({$skipped} dilewati)");
        }

        return redirect()->route('timesheet')->with('success', $msg);
    }

    public function update(Request $request, TimesheetMember $member)
    {
        $request->validate([
            'nama_override' => 'nullable|string|max:100',
            'urutan'        => 'nullable|integer',
            'aktif'         => 'boolean',
            'tipe'          => 'nullable|in:7jam,8jam',
            'sub_group'     => 'nullable|string|max:30',
            'kelompok'      => 'nullable|in:per_jam,flat',
        ]);

        $member->update($request->only(['nama_override', 'urutan', 'aktif', 'tipe', 'sub_group', 'kelompok']));

        $nama = $member->nama_override ?? $member->employee?->nama_lengkap ?? $member->id_badge;
        ActivityLog::record('update', 'Timesheet', $nama, "Update anggota timesheet: {$nama}");

        return back()->with('success', 'Data diperbarui.');
    }

    public function destroy(TimesheetMember $member)
    {
        $nama = $member->nama_override ?? $member->employee?->nama_lengkap ?? $member->id_badge;
        $member->delete();

        ActivityLog::record('delete', 'Timesheet', $nama, "Hapus anggota timesheet: {$nama}");

        return back()->with('success', 'Anggota dihapus dari daftar timesheet.');
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'members'          => 'required|array',
            'members.*.id'     => 'required|integer|exists:timesheet_members,id',
            'members.*.urutan' => 'required|integer',
        ]);

        foreach ($request->input('members') as $row) {
            TimesheetMember::where('id', $row['id'])->update(['urutan' => $row['urutan']]);
        }

        ActivityLog::record('update', 'Timesheet', 'Urutan Anggota', 'Ubah urutan anggota timesheet');

        return response()->json(['success' => true]);
    }
    
    public function importDefault()
    {
        $defaultBadges = [
            'AKM-EW-0096','AKM-EW-0065','AKM-EW-0099','AKM-EW-0097',
            'AKM-EW-0066','AKM-EW-0072','AKM-EW-0067','AKM-EW-0069',
            'AKM-EW-0074','AKM-EW-0100','AKM-EW-0101','AKM-EW-0064',
            'AKM-EW-0190','AKM-EW-0174','AKM-EW-0061','AKM-EW-0059',
            'AKM-EW-0057','AKM-EW-0110','AKM-EW-0119','AKM-EW-0051',
            'AKM-EW-0052','AKM-EW-0111','AKM-EW-0053','AKM-EW-0054',
            'AKM-EW-0258','AKM-EW-0060','AKM-EW-0129','AKM-EW-0194',
            'AKM-EW-0112','AKM-EW-0114','AKM-EW-0198','AKM-EW-0200',
            'AKM-EW-0169','AKM-EW-0109','AKM-EW-0170','AKM-EW-0232',
            'AKM-EW-0266','AKM-EW-0126','AKM-EW-0712','AKM-EW-0203',
            'AKM-EW-0202','AKM-EW-0657','AKM-EW-0713','AKM-EW-0197',
            'AKM-EW-0073','AKM-EW-0013','AKM-EW-0103','AKM-EW-0068',
            'AKM-EW-0102','AKM-EW-0117','AKM-EW-0062','AKM-EW-0113',
            'AKM-EW-0236','AKM-EW-0127','AKM-EW-0167',
        ];

        $namaOverride = [
            'AKM-EW-0236' => 'SASTRO',
            'AKM-EW-0197' => 'M YASIR SIREGAR',
            'AKM-EW-0073' => 'EVANDRI WANDA',
            'AKM-EW-0013' => 'M RAPIQI',
            'AKM-EW-0068' => 'YUDHA PRATAMA',
            'AKM-EW-0102' => 'JAMI MARUZUKI',
            'AKM-EW-0117' => 'M REVAL AL AKHYAR',
            'AKM-EW-0127' => 'MUHAMMAD RENO',
            'AKM-EW-0167' => 'M HUSNI IQBAL',
        ];

        $projectId = $this->activeProjectId();
        if (!$projectId && auth()->user()->hasRole('super-admin')) {
            $projectId = session('active_project_kode');
        }

        $project = \App\Models\Project::find($projectId);
        $tipe    = $project?->tipe_timesheet === '8jam' ? '8jam' : '7jam';

        $count = 0;
        foreach ($defaultBadges as $i => $badge) {
            $exists = TimesheetMember::where('id_badge', $badge)
                ->when($projectId, fn($q) => $q->where('project_id', $projectId))
                ->exists();

            if (!$exists) {
                TimesheetMember::create([
                    'id_badge'      => $badge,
                    'nama_override' => $namaOverride[$badge] ?? null,
                    'project_id'    => $projectId,
                    'tipe'          => $tipe,
                    'sub_group'     => null,
                    'kelompok'      => 'per_jam',
                    'urutan'        => $i + 1,
                    'aktif'         => true,
                ]);
                $count++;
            }
        }

        if ($count > 0) {
            ActivityLog::record('import', 'Timesheet', 'Import Default', "{$count} anggota default diimport ke timesheet");
        }

        return back()->with('success', "{$count} anggota berhasil diimport.");
    }
}