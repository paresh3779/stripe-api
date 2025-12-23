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

    /**
     * Find promo code by code string
     *
     * @param string $code
     * @return PromoCode|null
     */
    public function findByCode(string $code): ?PromoCode
    {
        return PromoCode::with('coupon')
            ->where('code', $code)
            ->first();
    }

    /**
     * Find promo code by ID
     *
     * @param string $id
     * @return PromoCode|null
     */
    public function findById(string $id): ?PromoCode
    {
        return PromoCode::with('coupon')->find($id);
    }

    /**
     * Increment redemptions count
     *
     * @param PromoCode $promoCode
     * @return void
     */
    public function incrementRedemptions(PromoCode $promoCode): void
    {
        $promoCode->increment('times_redeemed');
        
        if ($promoCode->coupon) {
            $promoCode->coupon->increment('times_redeemed');
        }
    }

    /**
     * Get active promo code by code string with all validations
     *
     * @param string $code
     * @return PromoCode|null
     */
    public function getActivePromoCodeByCode(string $code): ?PromoCode
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
            ->whereHas('coupon', function ($query) {
                $query->where('active', true);
            })
            ->first();
    }
}
