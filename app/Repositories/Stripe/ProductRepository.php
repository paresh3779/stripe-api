<?php

namespace App\Repositories\Stripe;

use App\Models\Product;
use App\Models\Price;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductRepository
{
    public function getActiveProducts(): Collection
    {
        return Product::where('active', true)
            ->where('is_archived', false)
            ->get();
    }

    public function getProductWithPrices(string $productId): ?Product
    {
        return Product::with(['prices' => function (HasMany $query): void {
            $query->where('active', true);
        }])
        ->where('id', $productId)
        ->where('active', true)
        ->where('is_archived', false)
        ->first();
    }

    public function getProductBySlug(string $slug): ?Product
    {
        return Product::with(['prices' => function (HasMany $query): void {
            $query->where('active', true);
        }])
        ->where('slug', $slug)
        ->where('active', true)
        ->where('is_archived', false)
        ->first();
    }

    public function getOneTimeProducts(): Collection
    {
        return Product::with(['prices' => function (HasMany $query): void {
            $query->where('active', true)->where('type', 'one_time');
        }])
        ->where('type', 'one_time')
        ->where('active', true)
        ->where('is_archived', false)
        ->get();
    }
}
