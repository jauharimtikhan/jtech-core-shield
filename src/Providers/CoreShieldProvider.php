<?php

namespace Jtech\CoreShield\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\Router;
use Jtech\CoreShield\Console\Commands\SystemUpdate;
use Jtech\CoreShield\Http\Middleware\CoreAppMiddleware;

use Illuminate\Database\QueryException;
use PDOException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use ReflectionClass;
use Exception;

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

        $this->maskDatabaseErrors();
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

    private function maskDatabaseErrors(): void
    {
        // Ambil instance Exception Handler milik Laravel
        $handler = $this->app->make(ExceptionHandler::class);

        // Bikin fungsi scrubber (penyensor)
        $scrubber = function (\Throwable $e) {
            $defaultConn = config('database.default');

            // Ambil data ASLI yang tadi kita suntikkan di Middleware
            $realDb   = config("database.connections.{$defaultConn}.database");
            $realUser = config("database.connections.{$defaultConn}.username");

            // Ambil data PALSU (Jebakan) murni dari file .env
            $dummyDb   = env('DB_DATABASE', 'jtech_dummy_db');
            $dummyUser = env('DB_USERNAME', 'root');

            if ($realDb && $realDb !== $dummyDb) {
                // Ganti kata-kata asli dengan yang palsu di dalam pesan error
                $message = $e->getMessage();
                $message = str_replace($realDb, $dummyDb, $message);
                $message = str_replace($realUser, $dummyUser, $message);

                // HACK: Gunakan PHP Reflection untuk meretas dan mengubah 
                // properti 'message' yang di-protect oleh PHP
                try {
                    $reflection = new ReflectionClass(Exception::class);
                    $property = $reflection->getProperty('message');
                    $property->setAccessible(true); // Paksa buka gembok protected
                    $property->setValue($e, $message); // Timpa pesannya!
                } catch (\Throwable $th) {
                    // Abaikan jika reflection gagal, biar aplikasi ngga tambah error
                }
            }
        };

        // 1. Cegat error SQL (misal: table not found, syntax error)
        $handler->reportable(function (QueryException $e) use ($scrubber) {
            $scrubber($e);
        });

        // 2. Cegat error Koneksi (misal: server down, access denied)
        $handler->reportable(function (PDOException $e) use ($scrubber) {
            $scrubber($e);
        });
    }
}
