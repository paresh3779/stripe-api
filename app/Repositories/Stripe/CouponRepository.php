<?php

declare(strict_types=1);

namespace App\Repositories\Stripe;

use App\Models\Coupon;

class CouponRepository
{
    public function getCouponById(string $couponId): ?Coupon
    {
        return Coupon::where('id', $couponId)
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

    public function incrementRedemption(string $couponId): ?Coupon
    {
        $coupon = Coupon::find($couponId);
        if ($coupon) {
            $coupon->increment('times_redeemed');
        }
        return $coupon;
    }
}
