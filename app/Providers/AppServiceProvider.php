<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Super-admin selalu lolos semua pengecekan permission menu, tanpa
        // perlu di-assign permission satu-satu (termasuk menu yang baru ditambah nanti).
        Gate::before(fn ($user, $ability) => $user->hasRole('super-admin') ? true : null);
    }
}
