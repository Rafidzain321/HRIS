<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \App\Http\Middleware\ProjectAccessMiddleware::class,
        ]);
        $middleware->alias([
            'payroll.access' => \App\Http\Middleware\CheckPayrollAccess::class,
            'menu'           => \App\Http\Middleware\CheckMenuPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->withCommands([
        \App\Console\Commands\ImportGajiNk::class,
        \App\Console\Commands\ImportTimesheetNk::class,
        \App\Console\Commands\UpdateInsentifNk::class,
        \App\Console\Commands\FixPayrollNk::class,
        \App\Console\Commands\FixKelompokNk::class,
    ])
    ->create();