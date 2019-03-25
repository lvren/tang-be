<?php

namespace App\Providers;

use App\Model\User;
use Cache;
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
     * @return void
     */
    public function boot()
    {
        // Here you may define how you wish users to be authenticated for your Lumen
        // application. The callback which receives the incoming request instance
        // should return either a User instance or null. You're free to obtain
        // the User instance via an API token or any other method necessary.

        $this->app['auth']->viaRequest('api', function ($request) {
            $session = $request->cookie('talksession');
            $sessionKey = $request->input('sessionKey');

            if ($session && Cache::has($session)) {
                // $userInfo = Cache::get($session);
                // return json_decode($userInfo);
                $userInfo = json_decode(Cache::get($session));
                // 用登录信息换取用户信息
                if (isset($userInfo->unionid) || isset($userInfo->unionId)) {
                    $unionid = isset($userInfo->unionid) ? $userInfo->unionid : $userInfo->unionId;
                    return User::where('unionid', $unionid)->first();
                } else if (isset($userInfo->openid)) {
                    return User::where('uuid', $userInfo->openid)->first();
                }
            } else if ($sessionKey && Cache::has($sessionKey)) {
                $userInfo = json_decode(Cache::get($sessionKey));
                // 用登录信息换取用户信息
                if (isset($userInfo->unionid) || isset($userInfo->unionId)) {
                    $unionid = isset($userInfo->unionid) ? $userInfo->unionid : $userInfo->unionId;
                    return User::where('unionid', $unionid)->first();
                } else if (isset($userInfo->openid)) {
                    return User::where('uuid', $userInfo->openid)->first();
                }
            }
        });
    }
}
