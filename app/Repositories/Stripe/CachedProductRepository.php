<?php

declare(strict_types=1);

namespace App\Repositories\Stripe;

use App\Services\Stripe\CacheService;
use Illuminate\Database\Eloquent\Collection;

/**
 * Cached product repository
 * Wraps ProductRepository with caching for improved performance
 */
class CachedProductRepository
{
    public function __construct(
        protected readonly ProductRepository $productRepository,
        protected readonly CacheService $cacheService
    ) {}

    /**
     * Get active one-time products (cached)
     *
     * @return Collection
     */
    public function getOneTimeProducts(): Collection
    {
        return $this->cacheService->rememberProducts('one_time', function () {
            return $this->productRepository->getOneTimeProducts();
        });
    }

    /**
     * Get product with prices by ID (cached)
     *
     * @param string $productId
     * @return object|null
     */
    public function getProductWithPrices(string $productId): ?object
    {
        return $this->cacheService->rememberProducts("product:{$productId}", function () use ($productId) {
            return $this->productRepository->getProductWithPrices($productId);
        });
    }

    /**
     * Invalidate product cache
     *
     * @param string|null $productId
     * @return void
     */
    public function invalidateCache(?string $productId = null): void
    {
        if ($productId) {
            $this->cacheService->forgetProducts("product:{$productId}");
        }
        $this->cacheService->forgetProducts('one_time');
    }
}
