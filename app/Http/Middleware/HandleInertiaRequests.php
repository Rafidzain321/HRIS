<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->roles->first()?->name,
                    'project_id' => $user->project_id,
                    'project_ids' => $user->project_ids ? json_decode($user->project_ids, true) : null,
                    'project' => $user->project ? [
                        'id' => $user->project->id,
                        'kode' => $user->project->kode,
                        'nama' => $user->project->nama,
                        'warna' => $user->project->warna,
                    ] : null,
                    'can' => [
                        'is_super_admin' => $user->hasRole('super-admin'),
                        'is_viewer' => $user->hasRole('viewer'),
                        'is_project_user' => $user->hasRole('project-user'),
                    ],
                ] : null,
            ],
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
                'import_result' => session('import_result'),
            ],
            'active_project_id' => session('active_project_kode'),
            'projects' => \App\Models\Project::where('is_active', true)
                ->orderBy('nama')
                ->get(['id', 'kode', 'nama', 'warna'])
                ->toArray(),
        ]);
    }
}
