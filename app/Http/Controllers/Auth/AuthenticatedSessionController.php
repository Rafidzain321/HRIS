<?php
// app/Http/Controllers/Auth/AuthenticatedSessionController.php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AuthenticatedSessionController extends Controller
{
    public function create()
    {
        return Inertia::render('Auth/Login');
    }

    public function store(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            // Log gagal login
            ActivityLog::create([
                'user_id'     => null,
                'action'      => 'login_failed',
                'module'      => 'Auth',
                'target_name' => $request->email,
                'description' => 'Gagal login: email atau password salah',
                'ip_address'  => $request->ip(),
            ]);

            return back()->withErrors([
                'email' => 'Email atau password salah.',
            ]);
        }

        $request->session()->regenerate();

        // Cek apakah user aktif
        if (!auth()->user()->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return back()->withErrors([
                'email' => 'Akun kamu telah dinonaktifkan. Hubungi administrator.',
            ]);
        }

        // Update last_login_at
        auth()->user()->update(['last_login_at' => now()]);

        // Log berhasil login
        ActivityLog::record('login', 'Auth', auth()->user()->name,
            'Login berhasil: ' . auth()->user()->email
        );

        return redirect()->intended('/');
    }

    public function destroy(Request $request)
    {
        // Log logout sebelum session dihapus
        if (auth()->check()) {
            ActivityLog::record('logout', 'Auth', auth()->user()->name,
                'Logout: ' . auth()->user()->email
            );
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }
}