<?php

declare(strict_types=1);

namespace App\Repositories\Stripe;

use App\Models\Product;
use App\Models\Price;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Repository for subscription-related product and price operations
 */
class SubscriptionRepository
{
    /**
     * Get all active subscription products with their recurring prices
     *
     * @return Collection
     */
    public function getSubscriptionProducts(): Collection
    {
        return Product::with(['prices' => function (HasMany $query): void {
            $query->where('active', true)
                  ->where('type', 'recurring');
        }])
        ->where('type', 'recurring')
        ->where('active', true)
        ->where('is_archived', false)
        ->get();
    }

    /**
     * Get a single subscription product with its recurring prices
     *
     * @param string $productId
     * @return Product|null
     */
    public function getSubscriptionProductById(string $productId): ?Product
    {
        return Product::with(['prices' => function (HasMany $query): void {
            $query->where('active', true)
                  ->where('type', 'recurring')
                  ->orderBy('interval')
                  ->orderBy('amount');
        }])
        ->where('id', $productId)
        ->where('type', 'recurring')
        ->where('active', true)
        ->where('is_archived', false)
        ->first();
    }

    /**
     * Get a subscription product by slug
     *
     * @param string $slug
     * @return Product|null
     */
    public function getSubscriptionProductBySlug(string $slug): ?Product
    {
        return Product::with(['prices' => function (HasMany $query): void {
            $query->where('active', true)
                  ->where('type', 'recurring')
                  ->orderBy('interval')
                  ->orderBy('amount');
        }])
        ->where('slug', $slug)
        ->where('type', 'recurring')
        ->where('active', true)
        ->where('is_archived', false)
        ->first();
    }

    /**
     * Get a recurring price by ID
     *
     * @param string $priceId
     * @return Price|null
     */
    public function getRecurringPriceById(string $priceId): ?Price
    {
        return Price::with('product')
            ->where('id', $priceId)
            ->where('type', 'recurring')
            ->where('active', true)
            ->first();
    }

    /**
     * Get prices by billing interval (monthly/yearly)
     *
     * @param string $productId
     * @param string $interval
     * @return Collection
     */
    public function getPricesByInterval(string $productId, string $interval): Collection
    {
        return Price::where('product_id', $productId)
            ->where('type', 'recurring')
            ->where('interval', $interval)
            ->where('active', true)
            ->get();
    }

    /**
     * Get prices with trial period
     *
     * @param string $productId
     * @return Collection
     */
    public function getPricesWithTrial(string $productId): Collection
    {
        return Price::where('product_id', $productId)
            ->where('type', 'recurring')
            ->where('active', true)
            ->whereNotNull('trial_days')
            ->where('trial_days', '>', 0)
            ->get();
    }

    /**
     * Get subscription products with trial support
     *
     * @return Collection
     */
    public function getSubscriptionProductsWithTrial(): Collection
    {
        return Product::with(['prices' => function (HasMany $query): void {
            $query->where('active', true)
                  ->where('type', 'recurring')
                  ->whereNotNull('trial_days')
                  ->where('trial_days', '>', 0);
        }])
        ->where('type', 'recurring')
        ->where('active', true)
        ->where('is_archived', false)
        ->whereHas('prices', function ($query) {
            $query->whereNotNull('trial_days')
                  ->where('trial_days', '>', 0);
        })
        ->get();
    }
}
