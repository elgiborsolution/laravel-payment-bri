
<?php

namespace Elgibor\BriQris;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class BriQrisServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/bri_qris.php', 'bri_qris');

        $this->app->singleton(BriQris::class, function($app) {
            return new BriQris($app['config']->get('bri_qris'));
        });
    }

    public function boot()
    {
        // Publish config
        $this->publishes([
            __DIR__.'/../config/bri_qris.php' => config_path('bri_qris.php'),
        ], 'bri-qris-config');

        // Routes for webhook/notify
        $cfg = $this->app['config']->get('bri_qris.notify_route');
        if (($cfg['enabled'] ?? false) === true) {
            Route::group([ 'middleware' => $cfg['middleware'] ?? ['api'] ], function() use ($cfg) {
                Route::post($cfg['uri'], [Http\Controllers\NotificationController::class, 'handle'])
                    ->name('bri.qris.notify');
            });
        }
    }
}
