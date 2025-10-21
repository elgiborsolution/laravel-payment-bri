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
        Route::aliasMiddleware('auth.b2b', \ESolution\BriPayments\Http\Middleware\AuthB2BMiddleware::class);
   
        $this->publishes([ __DIR__.'/../config/bri.php' => config_path('bri.php') ], 'bri-payments-config');

        Route::post('bri/get-signature', [Http\Controllers\AuthTokenB2BController::class, 'getSignature'])->name('bri.briva.signature');
        
        $cfgAuth = config('bri.briva.notify_auth');
        if (($cfgAuth['enabled'] ?? false) === true) {
            Route::group([], function() use ($cfgAuth) {
                Route::post($cfgAuth['uri'], [Http\Controllers\AuthTokenB2BController::class, 'handle'])->name('bri.briva.notify_auth');
            });
        }

        $cfgAuthTenant = config('bri.briva.notify_tenant_auth');
        if (($cfgAuthTenant['enabled'] ?? false) === true) {
            Route::group([], function() use ($cfgAuthTenant) {
                Route::post($cfgAuthTenant['uri'], [Http\Controllers\AuthTokenB2BController::class, 'handle'])->name('bri.briva.notify_tenant_auth');
            });
        }

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
        
        $cfg3 = config('bri.briva.notify_tenant');
        if (($cfg3['enabled'] ?? false) === true) {
            Route::group([ 'middleware' => $cfg3['middleware'] ?? ['api'] ], function() use ($cfg3) {
                Route::post($cfg3['uri'], [Http\Controllers\BrivaNotificationMultipleTenantController::class, 'handle'])->name('bri.briva.notify_tenant');
            });
        }
    }
}
