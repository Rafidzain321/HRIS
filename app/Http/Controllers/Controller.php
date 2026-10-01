<?php
namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

abstract class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    // Kantor yang sedang dibuka. Super-admin & viewer: dari pilihan di sidebar (null = semua kantor).
    // User multi-kantor: pilihan di sidebar kalau termasuk kantornya, kalau tidak kantor pertama.
    protected function activeProjectId(): ?int
    {
        $user = auth()->user();
        if (!$user) return null;

        if ($user->hasRole('super-admin') || $user->hasRole('viewer')) {
            return session('active_project_kode') ?: null;
        }

        $projectIds = $user->project_ids ? json_decode($user->project_ids, true) : null;
        if ($projectIds && count($projectIds) > 1) {
            $sessionPid = session('active_project_kode');
            return $sessionPid && in_array($sessionPid, $projectIds) ? $sessionPid : $projectIds[0];
        }

        return $user->project_id;
    }

    protected function applyProjectFilter($query)
    {
        $projectId = $this->activeProjectId();
        if ($projectId) {
            $query->where('project_id', $projectId);
        }
        return $query;
    }

    protected function isViewer(): bool
    {
        return auth()->user()?->hasRole('viewer') ?? false;
    }

    // Pengaturan versi lengkap (Manajemen User, Project, Jabatan, Log Aktivitas) — super-admin, role hr-staff,
    // atau HR dengan permission edit-kpi (Budi/Efendi/Ali). Selain itu cuma profil pribadi & ganti password.
    protected function isAdminSettings(): bool
    {
        $user = auth()->user();
        if (!$user) return false;

        return $user->hasRole('super-admin') || $user->hasRole('hr-staff') || $user->can('edit-kpi');
    }

    // Karyawan aktif Head Office (modul Cuti & Kehadiran yang khusus HO), urut nama.
    protected function karyawanAktifHo()
    {
        return Employee::aktif()->where('project_id', Project::where('kode', 'ho')->value('id'))
            ->with('position')->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'position_id']);
    }

    // ?highlight=id (mis. dari notifikasi): nomor halaman (50/halaman) yang memuat data tsb.
    protected function highlightPage($query, $highlight, Request $request): mixed
    {
        $page = $request->get('page', 1);
        if ($highlight) {
            $pos = array_search((int) $highlight, (clone $query)->pluck('id')->toArray());
            if ($pos !== false) $page = (int) floor($pos / 50) + 1;
        }
        return $page;
    }

    // Filter dokumen berdasarkan tanggal expired: expired / warning (< 30 hari) / ok / no_data.
    protected function filterExpiry($query, string $col, string $filter, bool $withNoData = false): void
    {
        $today = Carbon::today();
        $in30  = $today->copy()->addDays(30);
        match (true) {
            $filter === 'expired'               => $query->where($col, '<', $today),
            $filter === 'warning'               => $query->whereBetween($col, [$today, $in30]),
            $filter === 'ok'                    => $query->where($col, '>=', $in30),
            $filter === 'no_data' && $withNoData => $query->whereNull($col),
            default                             => null,
        };
    }

    // Jumlah expired / warning / ok / tanpa tanggal untuk kartu statistik.
    protected function expiryCounts($base, string $col): array
    {
        $today = Carbon::today();
        $in30  = $today->copy()->addDays(30);
        return [
            'expired' => (clone $base)->whereNotNull($col)->where($col, '<', $today)->count(),
            'warning' => (clone $base)->whereNotNull($col)->whereBetween($col, [$today, $in30])->count(),
            'ok'      => (clone $base)->whereNotNull($col)->where($col, '>=', $in30)->count(),
            'no_data' => (clone $base)->whereNull($col)->count(),
        ];
    }
}
