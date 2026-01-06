<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\Stripe\ProductRepository;
use App\Repositories\Stripe\SubscriptionRepository;
use App\Repositories\Stripe\CachedProductRepository;
use App\Repositories\Stripe\CachedSubscriptionRepository;
use App\Services\Stripe\CacheService;
use Stripe\Stripe;

/**
 * Service provider for Stripe-related services
 * Registers bindings and configures Stripe
 */
class StripeServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        // Register cache service as singleton
        $this->app->singleton(CacheService::class, function () {
            return new CacheService();
        });

        // Register cached repositories
        $this->app->singleton(CachedProductRepository::class, function ($app) {
            return new CachedProductRepository(
                $app->make(ProductRepository::class),
                $app->make(CacheService::class)
            );
        });

        $this->app->singleton(CachedSubscriptionRepository::class, function ($app) {
            return new CachedSubscriptionRepository(
                $app->make(SubscriptionRepository::class),
                $app->make(CacheService::class)
            );
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void
    {
        // Configure Stripe
        Stripe::setApiKey(config('stripe.secret'));
        
        // Set API version for consistency
        Stripe::setApiVersion('2023-10-16');

        // Set app info for Stripe dashboard
        Stripe::setAppInfo(
            config('app.name', 'Laravel App'),
            config('app.version', '1.0.0'),
            config('app.url')
        );
    }
}
