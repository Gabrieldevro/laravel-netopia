<?php

namespace Gabrieldev\Netopia;

use Gabrieldev\Netopia\Http\IpnController;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class NetopiaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/netopia.php', 'netopia');

        $this->app->singleton(NetopiaClient::class, fn ($app) => new NetopiaClient($app->make(Http::class), $app['config']['netopia']));

        $this->app->bind(IpnVerifier::class, fn ($app) => new IpnVerifier(
            (string) $app['config']['netopia.public_key'],
            (string) $app['config']['netopia.pos_signature'],
        ));
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/netopia.php' => config_path('netopia.php')], 'netopia-config');

        // Route::netopiaIpn('plati/netopia/ipn') dacă vrei altă adresă sau alt middleware.
        Route::macro('netopiaIpn', fn (string $uri = 'netopia/ipn') => Route::post($uri, IpnController::class)->name('netopia.ipn'));

        if ($uri = config('netopia.ipn_route')) {
            // Fără middleware-ul „web”: NETOPIA nu trimite token CSRF.
            Route::post($uri, IpnController::class)->name('netopia.ipn');
        }
    }
}
