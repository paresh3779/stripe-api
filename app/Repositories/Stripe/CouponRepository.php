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

    /**
     * Get all active coupons
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getActiveCoupons(): \Illuminate\Database\Eloquent\Collection
    {
        return Coupon::where('active', true)
            ->where(function ($query) {
                $query->whereNull('valid_until')
                    ->orWhere('valid_until', '>', now());
            })
            ->where(function ($query) {
                $query->whereNull('max_redemptions')
                    ->orWhereRaw('times_redeemed < max_redemptions');
            })
            ->get();
    }

    /**
     * Find coupon by ID
     *
     * @param string $id
     * @return Coupon|null
     */
    public function findById(string $id): ?Coupon
    {
        return Coupon::find($id);
    }

    /**
     * Increment redemptions count
     *
     * @param Coupon $coupon
     * @return void
     */
    public function incrementRedemptions(Coupon $coupon): void
    {
        $coupon->increment('times_redeemed');
    }
}
