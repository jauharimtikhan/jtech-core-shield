<?php

namespace Jtech\CoreShield\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\Router;
use Jtech\CoreShield\Console\Commands\SystemUpdate;
use Jtech\CoreShield\Http\Middleware\CoreAppMiddleware;

class CoreShieldProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Di sini lo bisa merge config bawaan package kalau ada,
        // misalnya default serverUrl API lisensi lo.
        // $this->mergeConfigFrom(__DIR__.'/../config/coreshield.php', 'coreshield');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(Router $router): void
    {
        // 1. INJEKSI MIDDLEWARE SECARA PAKSA (SILENT INJECTION)
        // Client nggak perlu daftarin manual di bootstrap/app.php
        // Middleware ini otomatis jalan duluan di semua request Web dan API
        $router->pushMiddlewareToGroup('web', CoreAppMiddleware::class);
        $router->pushMiddlewareToGroup('api', CoreAppMiddleware::class);
        $router->pushMiddlewareToGroup('guest', CoreAppMiddleware::class);
        $router->pushMiddlewareToGroup('auth', CoreAppMiddleware::class);

        // 2. REGISTER ARTISAN COMMAND
        // Command ini cuma di-load kalau aplikasi dijalankan lewat CLI (Terminal)
        if ($this->app->runningInConsole()) {
            $this->commands([
                SystemUpdate::class,
            ]);
        }

        // 3. LOAD CUSTOM VIEWS
        // Biar halaman error 403 "Sistem Terkunci" tadi bisa dipanggil dari dalam package
        // Pastikan lo masukin file blade-nya di dalam folder package: resources/views/errors/license.blade.php
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'coreshield');

        // Opsional: Publish view kalau client butuh custom tampilannya
        // $this->publishes([
        //     __DIR__.'/../resources/views' => resource_path('views/vendor/coreshield'),
        // ], 'coreshield-views');
    }
}
