<?php

namespace App\Providers;

use App\Repositories\CategoryRepository;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Repositories\Contracts\PriceUpdateRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\CustomerRepository;
use App\Repositories\PriceUpdateRepository;
use App\Repositories\ProductRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Repository Service Provider
 * 
 * Binds repository interfaces to their implementations.
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
        $this->app->bind(CategoryRepositoryInterface::class, CategoryRepository::class);
        $this->app->bind(ProductRepositoryInterface::class, ProductRepository::class);
        $this->app->bind(CustomerRepositoryInterface::class, CustomerRepository::class);
        $this->app->bind(PriceUpdateRepositoryInterface::class, PriceUpdateRepository::class);
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

