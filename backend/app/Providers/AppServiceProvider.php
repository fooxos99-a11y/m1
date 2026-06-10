<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;

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
        RateLimiter::for('login', function (Request $request) {
            $loginCode = (string) $request->input('login_code', '');

            return Limit::perMinute(5)->by($loginCode.'|'.$request->ip());
        });

        Fortify::authenticateUsing(function (Request $request): ?User {
            $loginCode = trim((string) $request->input('login_code', ''));
            $password = (string) $request->input('password', '');

            if ($loginCode === '' || $password === '') {
                return null;
            }

            $user = User::query()
                ->where('login_code', $loginCode)
                ->first();

            if (! $user || ! $user->password || ! Hash::check($password, $user->password)) {
                return null;
            }

            return $user;
        });
    }
}
