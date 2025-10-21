<?php

namespace ESolution\BriPayments;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use ESolution\BriPayments\Qris\QrisClient;
use ESolution\BriPayments\Briva\BrivaClient;
use ESolution\BriPayments\Http\Controllers\AuthTokenB2BController;
use ESolution\BriPayments\Http\Controllers\QrisNotificationController;
use ESolution\BriPayments\Http\Controllers\BrivaNotificationController;
use ESolution\BriPayments\Http\Controllers\BrivaNotificationMultipleTenantController;
use ESolution\BriPayments\Http\Middleware\AuthB2BMiddleware;

class BriPaymentsServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/bri.php', 'bri');

        $this->app->singleton(QrisClient::class, fn ($app) =>
            new QrisClient($app['config']->get('bri'))
        );

        $this->app->singleton(BrivaClient::class, fn ($app) =>
            new BrivaClient($app['config']->get('bri'))
        );
    }

    public function boot()
    {
        // ✅ Register Middleware
        $this->app['router']->aliasMiddleware('auth.b2b', AuthB2BMiddleware::class);

        // ✅ Publish Config
        $this->publishes([
            __DIR__ . '/../config/bri.php' => config_path('bri.php'),
        ], 'bri-payments-config');

        // ✅ Publish Migrations
        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'bri-payments-migrations');

        // ✅ Public Routes (Tanpa Middleware)
        Route::post('bri/get-signature-auth', [AuthTokenB2BController::class, 'getSignatureAuth'])
            ->name('bri.briva.signature-auth');

        // ✅ Protected Routes (Dengan Middleware auth.b2b)
        Route::group(['middleware' => 'auth.b2b'], function () {
            Route::post('bri/get-signature', [AuthTokenB2BController::class, 'getSignature'])
                ->name('bri.briva.signature');
            Route::post('bri/{tenant}/get-signature', [AuthTokenB2BController::class, 'getSignature'])
                ->name('bri.briva.signature-tenant');
        });

        // ✅ Notification Auth (No Tenant)
        $cfgAuth = config('bri.briva.notify_auth');
        if ($cfgAuth['enabled'] ?? false) {
            Route::post($cfgAuth['uri'], [AuthTokenB2BController::class, 'handle'])
                ->name('bri.briva.notify_auth');
        }

        // ✅ Notification Auth (With Tenant)
        $cfgAuthTenant = config('bri.briva.notify_tenant_auth');
        if ($cfgAuthTenant['enabled'] ?? false) {
            Route::post($cfgAuthTenant['uri'], [AuthTokenB2BController::class, 'handle'])
                ->name('bri.briva.notify_tenant_auth');
        }

        // ✅ QRIS Notification
        $cfgQris = config('bri.qris.notify');
        if ($cfgQris['enabled'] ?? false) {
            Route::group(['middleware' => $cfgQris['middleware'] ?? ['api']], function () use ($cfgQris) {
                Route::post($cfgQris['uri'], [QrisNotificationController::class, 'handle'])
                    ->name('bri.qris.notify');
            });
        }

        // ✅ BRIVA Notification (Single Tenant)
        $cfgBriva = config('bri.briva.notify');
        if ($cfgBriva['enabled'] ?? false) {
            Route::group(['middleware' => $cfgBriva['middleware'] ?? ['api']], function () use ($cfgBriva) {
                Route::post($cfgBriva['uri'], [BrivaNotificationController::class, 'handle'])
                    ->name('bri.briva.notify');
            });
        }

        // ✅ BRIVA Notification (Multiple Tenant)
        $cfgBrivaTenant = config('bri.briva.notify_tenant');
        if ($cfgBrivaTenant['enabled'] ?? false) {
            Route::group(['middleware' => $cfgBrivaTenant['middleware'] ?? ['api']], function () use ($cfgBrivaTenant) {
                Route::post($cfgBrivaTenant['uri'], [BrivaNotificationMultipleTenantController::class, 'handle'])
                    ->name('bri.briva.notify_tenant');
            });
        }
    }
}
