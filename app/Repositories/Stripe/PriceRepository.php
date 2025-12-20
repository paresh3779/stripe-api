<?php

declare(strict_types=1);

namespace App\Repositories\Stripe;

use App\Models\Price;
use Illuminate\Database\Eloquent\Collection;

class PriceRepository
{
    public function getPriceById(string $priceId): ?Price
    {
        return Price::with('product')
            ->where('id', $priceId)
            ->where('active', true)
            ->first();
    }

    public function getOneTimePrices(): Collection
    {
        return Price::with('product')
            ->where('type', 'one_time')
            ->where('active', true)
            ->get();
    }
}
