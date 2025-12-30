<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Repository Service Provider
 * 
 * No longer needed - repositories are injected directly.
 */
class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void
    {
        //
    }
}

