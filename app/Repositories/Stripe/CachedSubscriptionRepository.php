<?php

declare(strict_types=1);

namespace App\Repositories\Stripe;

use App\Services\Stripe\CacheService;
use App\Models\Price;
use Illuminate\Database\Eloquent\Collection;

/**
 * Cached subscription repository
 * Wraps SubscriptionRepository with caching for improved performance
 */
class CachedSubscriptionRepository
{
    public function __construct(
        protected readonly SubscriptionRepository $subscriptionRepository,
        protected readonly CacheService $cacheService
    ) {}

    /**
     * Get subscription products (cached)
     *
     * @return Collection
     */
    public function getSubscriptionProducts(): Collection
    {
        return $this->cacheService->rememberProducts('subscription', function () {
            return $this->subscriptionRepository->getSubscriptionProducts();
        });
    }

    /**
     * Get subscription products with trial (cached)
     *
     * @return Collection
     */
    public function getSubscriptionProductsWithTrial(): Collection
    {
        return $this->cacheService->rememberProducts('subscription_trial', function () {
            return $this->subscriptionRepository->getSubscriptionProductsWithTrial();
        });
    }

    /**
     * Get subscription product by ID (cached)
     *
     * @param string $productId
     * @return object|null
     */
    public function getSubscriptionProductById(string $productId): ?object
    {
        return $this->cacheService->rememberProducts("subscription:{$productId}", function () use ($productId) {
            return $this->subscriptionRepository->getSubscriptionProductById($productId);
        });
    }

    /**
     * Get recurring price by ID (not cached - used for validation)
     *
     * @param string $priceId
     * @return Price|null
     */
    public function getRecurringPriceById(string $priceId): ?Price
    {
        return $this->subscriptionRepository->getRecurringPriceById($priceId);
    }

    /**
     * Invalidate subscription cache
     *
     * @param string|null $productId
     * @return void
     */
    public function invalidateCache(?string $productId = null): void
    {
        if ($productId) {
            $this->cacheService->forgetProducts("subscription:{$productId}");
        }
        $this->cacheService->forgetProducts('subscription');
        $this->cacheService->forgetProducts('subscription_trial');
    }
}
