<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Dedoc\Scramble\Scramble;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (class_exists(Scramble::class)) {
            Gate::define('viewApiDocs', fn () => true);

            Scramble::configure()
                ->routes(fn ($route) => str_starts_with($route->uri(), 'api/'));
        }
    }
}
