<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPayrollAccess
{
    public function handle(Request $request, Closure $next): mixed
    {
        if ($request->user()?->restrict_payroll) {
            abort(403, 'Akun ini tidak memiliki akses ke Slip Gaji / Data Gaji.');
        }
        return $next($request);
    }
}
