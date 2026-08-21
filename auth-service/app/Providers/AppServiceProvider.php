<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Dedoc\Scramble\Scramble;

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
        if (class_exists(Scramble::class)) {
            // Allow public viewing of API documentation on cloud deployments
            Gate::define('viewApiDocs', fn () => true);

            Scramble::configure()
                ->routes(fn ($route) => str_starts_with($route->uri(), 'api/'));
        }
    }
}
