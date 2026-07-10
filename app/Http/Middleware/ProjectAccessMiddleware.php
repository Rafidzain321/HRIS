<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ProjectAccessMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Kalau belum login, biarkan middleware auth yang handle
        if (!auth()->check()) {
            return $next($request);
        }

        $user = auth()->user();

        // Super admin dan viewer bisa akses semua
        if ($user->hasRole('super-admin') || $user->hasRole('viewer')) {
            return $next($request);
        }

        // Project user — set session project aktif
        if ($user->project_id) {
            session(['active_project_id' => $user->project_id]);
        }

        return $next($request);
    }
}