<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckMenuPermission
{
    public function handle(Request $request, Closure $next, string $menu, string $action = 'view'): mixed
    {
        $user = $request->user();
        $permission = "{$action}-{$menu}";
        if (!$user || !$user->can($permission)) {
            abort(403, 'Anda tidak memiliki izin untuk mengakses halaman ini.');
        }

        // Hak edit cuma berlaku untuk project asal user sendiri (project_id) — bukan project
        // manapun yang lagi ditampilkan lewat switch-project (activeProjectId() bisa berpindah
        // untuk user multi-project via project_ids). Supaya user yang dikasih akses lihat ke
        // banyak project tetap read-only di project selain miliknya sendiri.
        if ($action === 'edit' && !$user->hasRole('super-admin') && !$user->hasRole('viewer')) {
            $projectIds = $user->project_ids ? json_decode($user->project_ids, true) : null;
            if (is_array($projectIds) && count($projectIds) > 1) {
                $activeProjectId = session('active_project_kode') ?: ($projectIds[0] ?? null);
                if ((int) $activeProjectId !== (int) $user->project_id) {
                    abort(403, 'Anda hanya memiliki akses lihat (bukan edit) untuk project ini.');
                }
            }
        }

        return $next($request);
    }
}
