<?php
namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

abstract class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
    protected function activeProjectId(): ?int
    {
        $user = auth()->user();
        if (!$user) return null;

        if ($user->hasRole('super-admin')) {
            return session('active_project_kode') ?: null;
        }
        if ($user->hasRole('viewer')) {
            return session('active_project_kode') ?: null;
        }

        // Multi-project user: gunakan session untuk switch, default ke project pertama
        $projectIds = $user->project_ids ? json_decode($user->project_ids, true) : null;
        if ($projectIds && count($projectIds) > 1) {
            $sessionPid = session('active_project_kode');
            if ($sessionPid && in_array($sessionPid, $projectIds)) {
                return $sessionPid;
            }
            return $projectIds[0];
        }

        return $user->project_id;
    }

    /**
     * Apply project filter ke query Eloquent.
     */
    protected function applyProjectFilter($query)
    {
        $projectId = $this->activeProjectId();
        if ($projectId) {
            $query->where('project_id', $projectId);
        }
        return $query;
    }

    /**
     * Cek apakah user viewer (read-only).
     */
    protected function isViewer(): bool
    {
        return auth()->user()?->hasRole('viewer') ?? false;
    }

    /**
     * Cek apakah user boleh kelola Pengaturan versi lengkap (Manajemen User, Project, Jabatan,
     * Log Aktivitas semua HO) — super-admin, role hr-staff, atau HR dengan permission edit-kpi
     * (Budi/Efendi/Ali). Selain itu (manager, project-user, viewer) cuma dapat versi sederhana
     * berupa profil pribadi (nama, email, ganti password).
     */
    protected function isAdminSettings(): bool
    {
        $user = auth()->user();
        if (!$user) return false;

        return $user->hasRole('super-admin') || $user->hasRole('hr-staff') || $user->can('edit-kpi');
    }
}