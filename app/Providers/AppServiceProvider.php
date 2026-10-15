<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Email server settings from Admin > System > Email server (when switched on) replace .env MAIL_*
        \App\Support\MailSettings::apply();

        // Audit log: admin sign-ins (participants have their own sign-in history)
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Login::class, function ($e) {
            if ($e->guard === 'web') \App\Support\Audit::event('auth', 'Signed in', $e->user);
        });
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Logout::class, function ($e) {
            if ($e->guard === 'web' && $e->user) \App\Support\Audit::event('auth', 'Signed out', $e->user);
        });
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Failed::class, function ($e) {
            if ($e->guard === 'web') \App\Support\Audit::event('auth', 'Failed sign-in', $e->user, ['email' => \Illuminate\Support\Str::limit((string) ($e->credentials['email'] ?? ''), 120)]);
        });
    }
}
