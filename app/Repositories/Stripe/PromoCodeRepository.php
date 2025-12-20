<?php

declare(strict_types=1);

namespace App\Repositories\Stripe;

use App\Models\PromoCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromoCodeRepository
{
    public function getPromoCodeByCode(string $code): ?PromoCode
    {
        return PromoCode::with('coupon')
            ->where('code', $code)
            ->where('active', true)
            ->where(function ($query) {
                $query->whereNull('valid_until')
                    ->orWhere('valid_until', '>', now());
            })
            ->where(function ($query) {
                $query->whereNull('max_redemptions')
                    ->orWhereRaw('times_redeemed < max_redemptions');
            })
            ->first();
    }

    public function incrementRedemption(string $promoCodeId): ?PromoCode
    {
        $promoCode = PromoCode::find($promoCodeId);
        if ($promoCode) {
            $promoCode->increment('times_redeemed');
        }
        return $promoCode;
    }
}
