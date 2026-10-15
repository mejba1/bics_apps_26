<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Every permission in App\Support\Permissions is a Gate ability, decided by the user's role
        \Illuminate\Support\Facades\Gate::before(function ($user, string $ability) {
            if (\App\Support\Permissions::exists($ability)) {
                return method_exists($user, 'hasPermission') && $user->hasPermission($ability);
            }
            return null; // not one of ours: let other gates / policies decide
        });
    }
}
