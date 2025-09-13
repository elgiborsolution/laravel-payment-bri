
<?php

namespace ESolution\BriPayments;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use ESolution\BriPayments\Qris\QrisClient;
use ESolution\BriPayments\Briva\BrivaClient;

class BriPaymentsServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/bri.php', 'bri');
        $this->app->singleton(QrisClient::class, fn($app) => new QrisClient($app['config']->get('bri')));
        $this->app->singleton(BrivaClient::class, fn($app) => new BrivaClient($app['config']->get('bri')));
    }

    public function boot()
    {
        $this->publishes([ __DIR__.'/../config/bri.php' => config_path('bri.php') ], 'bri-payments-config');

        $cfg = config('bri.qris.notify');
        if (($cfg['enabled'] ?? false) === true) {
            Route::group([ 'middleware' => $cfg['middleware'] ?? ['api'] ], function() use ($cfg) {
                Route::post($cfg['uri'], [Http\Controllers\QrisNotificationController::class, 'handle'])->name('bri.qris.notify');
            });
        }

        $cfg2 = config('bri.briva.notify');
        if (($cfg2['enabled'] ?? false) === true) {
            Route::group([ 'middleware' => $cfg2['middleware'] ?? ['api'] ], function() use ($cfg2) {
                Route::post($cfg2['uri'], [Http\Controllers\BrivaNotificationController::class, 'handle'])->name('bri.briva.notify');
            });
        }
    }
}
