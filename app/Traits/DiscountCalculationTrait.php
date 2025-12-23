<?php

declare(strict_types=1);

namespace App\Traits;

use App\Constants\DiscountType;
use App\Models\Coupon;
use App\Models\PromoCode;

/**
 * Trait for discount calculations
 * Provides reusable discount calculation logic
 */
trait DiscountCalculationTrait
{
    /**
     * Calculate discount amount based on coupon
     *
     * @param int $originalAmount Amount in cents
     * @param Coupon $coupon
     * @return int Discount amount in cents
     */
    protected function calculateCouponDiscount(int $originalAmount, Coupon $coupon): int
    {
        return $this->calculateDiscountAmount(
            $originalAmount,
            $coupon->discount_type,
            $coupon->discount_value
        );
    }

    /**
     * Calculate discount amount based on promo code
     *
     * @param int $originalAmount Amount in cents
     * @param PromoCode $promoCode
     * @return int Discount amount in cents
     */
    protected function calculatePromoCodeDiscount(int $originalAmount, PromoCode $promoCode): int
    {
        if (!$promoCode->coupon) {
            return 0;
        }

        return $this->calculateCouponDiscount($originalAmount, $promoCode->coupon);
    }

    /**
     * Calculate discount amount
     *
     * @param int $originalAmount Amount in cents
     * @param string $discountType 'percentage' or 'fixed'
     * @param int $discountValue Percentage (0-100) or fixed amount in cents
     * @return int Discount amount in cents
     */
    protected function calculateDiscountAmount(int $originalAmount, string $discountType, int $discountValue): int
    {
        if ($discountType === DiscountType::PERCENTAGE) {
            $discount = (int) round(($originalAmount * $discountValue) / 100);
        } else {
            $discount = $discountValue;
        }

        // Ensure discount doesn't exceed original amount
        return min($discount, $originalAmount);
    }

    /**
     * Calculate final amount after discount
     *
     * @param int $originalAmount
     * @param int $discountAmount
     * @return int
     */
    protected function calculateFinalAmount(int $originalAmount, int $discountAmount): int
    {
        return max(0, $originalAmount - $discountAmount);
    }

    /**
     * Build discount calculation response
     *
     * @param int $originalAmount
     * @param int $discountAmount
     * @param string $currency
     * @return array
     */
    protected function buildDiscountResponse(int $originalAmount, int $discountAmount, string $currency = 'usd'): array
    {
        return [
            'original_amount' => $originalAmount,
            'discount_amount' => $discountAmount,
            'final_amount' => $this->calculateFinalAmount($originalAmount, $discountAmount),
            'currency' => $currency,
        ];
    }
}
