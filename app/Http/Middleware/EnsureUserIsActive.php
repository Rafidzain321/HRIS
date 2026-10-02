<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

// Status aktif dicek di setiap request, bukan cuma saat login — user yang dinonaktifkan
// (mis. resign) langsung terputus walaupun sesi / cookie "Ingat saya"-nya masih hidup.
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && !$user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect('/login')->withErrors(['email' => 'Akun kamu telah dinonaktifkan. Hubungi administrator.']);
        }

        return $next($request);
    }
}
