<?php

namespace App\Providers;

use App\Services\AuthService;
use App\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Boot the authentication services for the application.
     *
     * @param AuthService $authService
     * @return void
     */
    public function boot(AuthService $authService)
    {
        // Here you may define how you wish users to be authenticated for your Lumen
        // application. The callback which receives the incoming request instance
        // should return either a User instance or null. You're free to obtain
        // the User instance via an API token or any other method necessary.
        $this->app['auth']->viaRequest('api', function ($request) use ($authService) {
            $response = $authService->authenticate($request);
            if ($response['status'] == 200 && $response['data']) {
                return User::setup($response['data']);
            }
            return null;
        });
    }
}
