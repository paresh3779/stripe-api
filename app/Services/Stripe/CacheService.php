<?php

declare(strict_types=1);

namespace App\Services\Stripe;

use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Collection;

/**
 * Caching service for Stripe-related data
 * Improves performance by caching frequently accessed data
 */
class CacheService
{
    /**
     * Cache TTL in seconds (5 minutes for products/prices)
     */
    private const PRODUCT_CACHE_TTL = 300;

    /**
     * Cache TTL for coupons (1 minute - shorter due to redemption limits)
     */
    private const COUPON_CACHE_TTL = 60;

    /**
     * Cache key prefixes
     */
    private const PREFIX_PRODUCTS = 'stripe:products:';
    private const PREFIX_PRICES = 'stripe:prices:';
    private const PREFIX_COUPONS = 'stripe:coupons:';
    private const PREFIX_PROMO = 'stripe:promo:';

    /**
     * Get cached products or execute callback
     *
     * @param string $key
     * @param callable $callback
     * @return mixed
     */
    public function rememberProducts(string $key, callable $callback): mixed
    {
        return Cache::remember(
            self::PREFIX_PRODUCTS . $key,
            self::PRODUCT_CACHE_TTL,
            $callback
        );
    }

    /**
     * Get cached prices or execute callback
     *
     * @param string $key
     * @param callable $callback
     * @return mixed
     */
    public function rememberPrices(string $key, callable $callback): mixed
    {
        return Cache::remember(
            self::PREFIX_PRICES . $key,
            self::PRODUCT_CACHE_TTL,
            $callback
        );
    }

    /**
     * Get cached coupons or execute callback
     *
     * @param string $key
     * @param callable $callback
     * @return mixed
     */
    public function rememberCoupons(string $key, callable $callback): mixed
    {
        return Cache::remember(
            self::PREFIX_COUPONS . $key,
            self::COUPON_CACHE_TTL,
            $callback
        );
    }

    /**
     * Forget product cache
     *
     * @param string|null $key
     * @return void
     */
    public function forgetProducts(?string $key = null): void
    {
        if ($key) {
            Cache::forget(self::PREFIX_PRODUCTS . $key);
        } else {
            $this->forgetByPrefix(self::PREFIX_PRODUCTS);
        }
    }

    /**
     * Forget coupon cache
     *
     * @param string|null $key
     * @return void
     */
    public function forgetCoupons(?string $key = null): void
    {
        if ($key) {
            Cache::forget(self::PREFIX_COUPONS . $key);
        } else {
            $this->forgetByPrefix(self::PREFIX_COUPONS);
        }
    }

    /**
     * Forget promo code cache
     *
     * @param string|null $key
     * @return void
     */
    public function forgetPromoCode(?string $key = null): void
    {
        if ($key) {
            Cache::forget(self::PREFIX_PROMO . $key);
        } else {
            $this->forgetByPrefix(self::PREFIX_PROMO);
        }
    }

    /**
     * Forget all Stripe cache
     *
     * @return void
     */
    public function forgetAll(): void
    {
        $this->forgetByPrefix('stripe:');
    }

    /**
     * Forget cache by prefix (for cache drivers that support tags)
     *
     * @param string $prefix
     * @return void
     */
    protected function forgetByPrefix(string $prefix): void
    {
        // For Redis/Memcached with tag support, use tags
        // For file cache, this will need to iterate keys
        if (method_exists(Cache::getStore(), 'tags')) {
            Cache::tags([$prefix])->flush();
        }
    }
}
