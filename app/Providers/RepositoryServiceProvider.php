<?php

namespace App\Providers;

use App\Repositories\CategoryRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\OrderRepository;
use App\Repositories\PriceUpdateRepository;
use App\Repositories\ProductRepository;
use App\Repositories\RepositoryInterface;
use App\Services\AddressService;
use App\Services\AuthService;
use App\Services\CacheService;
use App\Services\CategoryService;
use App\Services\DashboardService;
use App\Services\GeoapifyService;
use App\Services\CustomerService;
use App\Services\ImageService;
use App\Services\OrderPdfService;
use App\Services\OrderService;
use App\Services\PdfService;
use App\Services\PriceUpdateService;
use App\Services\ProductFilterService;
use App\Services\ProductService;
use App\Services\TwilioService;
use App\Services\WhatsAppService;
use Illuminate\Support\ServiceProvider;

/**
 * Repository Service Provider
 * 
 * Registers all repositories and services in the service container.
 * This allows for:
 * - Dependency injection
 * - Interface binding (when interfaces are created)
 * - Easy swapping of implementations
 * - Better testability
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
        $this->registerRepositories();
        $this->registerServices();
    }

    /**
     * Register all repositories.
     * 
     * Repositories are bound as regular instances (not singleton) to allow
     * for flexibility and avoid potential state issues.
     *
     * @return void
     */
    protected function registerRepositories(): void
    {
        // Bind repositories directly (can be changed to interface binding later)
        $this->app->bind(ProductRepository::class);
        $this->app->bind(CategoryRepository::class);
        $this->app->bind(CustomerRepository::class);
        $this->app->bind(OrderRepository::class);
        $this->app->bind(PriceUpdateRepository::class);
    }

    /**
     * Register all services.
     * 
     * Services are bound as singletons when they are:
     * - Stateless (no instance state)
     * - Resource-intensive to instantiate
     * - Cache-heavy (benefit from shared instance)
     * 
     * Regular bindings are used for services that might need
     * different instances per request or have state.
     *
     * @return void
     */
    protected function registerServices(): void
    {
        // Core business logic services
        // These are stateless and can be singletons for performance
        $this->app->singleton(ProductService::class);
        $this->app->singleton(CategoryService::class);
        $this->app->singleton(CustomerService::class);
        $this->app->singleton(PriceUpdateService::class);
        $this->app->singleton(OrderService::class);
        $this->app->singleton(DashboardService::class);
        $this->app->singleton(AuthService::class);

        // Utility services (stateless, singleton for performance)
        $this->app->singleton(ImageService::class);
        $this->app->singleton(CacheService::class);
        
        // External API services (stateless, singleton for performance)
        $this->app->singleton(GeoapifyService::class);
        $this->app->singleton(AddressService::class);
        $this->app->singleton(ProductFilterService::class);
        
        // External API services (stateless, singleton for performance)
        $this->app->singleton(TwilioService::class);
        $this->app->singleton(WhatsAppService::class);

        // PDF services (resource-intensive, singleton for performance)
        $this->app->singleton(PdfService::class);
        $this->app->singleton(OrderPdfService::class);
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
