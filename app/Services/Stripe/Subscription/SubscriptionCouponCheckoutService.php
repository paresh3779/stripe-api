<?php

declare(strict_types=1);

namespace App\Services\Stripe\Subscription;

use App\Constants\SubscriptionMessages;
use App\Models\User;
use App\Repositories\Stripe\SubscriptionRepository;
use App\Repositories\Stripe\StripeCustomerRepository;
use App\Repositories\Stripe\CouponRepository;

/**
 * Service for subscription checkout with coupon discount
 * Demo 3: Product with coupon
 */
class SubscriptionCouponCheckoutService extends BaseSubscriptionCheckoutService
{
    protected CouponRepository $couponRepository;

    public function __construct(
        SubscriptionRepository $subscriptionRepository,
        StripeCustomerRepository $customerRepository,
        CouponRepository $couponRepository
    ) {
        parent::__construct($subscriptionRepository, $customerRepository);
        $this->couponRepository = $couponRepository;
    }

    /**
     * Get all active coupons applicable for subscriptions
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getCoupons(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->couponRepository->getActiveCoupons();
    }

    /**
     * Validate a coupon and return its details
     *
     * @param string $couponId
     * @return array
     * @throws \Exception
     */
    public function validateCoupon(string $couponId): array
    {
        $coupon = $this->couponRepository->getCouponById($couponId);

        if (!$coupon) {
            throw new \Exception(SubscriptionMessages::COUPON_INVALID);
        }

        if (!$coupon->active) {
            throw new \Exception(SubscriptionMessages::COUPON_NOT_ACTIVE);
        }

        return [
            'valid' => true,
            'coupon' => $coupon,
        ];
    }

    /**
     * Create a subscription checkout session with coupon applied
     *
     * @param string $priceId The Stripe price ID for the subscription
     * @param string $couponId The coupon ID to apply
     * @param User|null $user The authenticated user
     * @return array Contains sessionId and redirect URL
     * @throws \Exception
     */
    public function createCheckoutSession(string $priceId, string $couponId, ?User $user = null): array
    {
        $price = $this->validateRecurringPrice($priceId);
        
        $coupon = $this->couponRepository->getCouponById($couponId);
        if (!$coupon) {
            throw new \Exception(SubscriptionMessages::COUPON_INVALID);
        }

        $sessionConfig = $this->buildBaseSessionConfig(
            $price->stripe_price_id,
            '/main/stripe-subscription-checkout/coupon/success',
            '/main/stripe-subscription-checkout/coupon'
        );

        $sessionConfig['discounts'] = [[
            'coupon' => $coupon->stripe_coupon_id,
        ]];

        $sessionConfig['metadata'] = [
            'product_id' => $price->product_id,
            'price_id' => $price->id,
            'product_name' => $price->product->name,
            'billing_interval' => $price->interval,
            'coupon_id' => $coupon->id,
            'coupon_code' => $coupon->name,
        ];

        if ($user) {
            $stripeCustomerId = $this->getOrCreateStripeCustomer($user);
            $sessionConfig['customer'] = $stripeCustomerId;
            $sessionConfig['metadata']['user_id'] = $user->id;
        }

        return $this->createSession($sessionConfig);
    }

    /**
     * Calculate discounted price based on coupon
     *
     * @param int $originalAmount Amount in cents
     * @param string $couponId
     * @return array
     * @throws \Exception
     */
    public function calculateDiscount(int $originalAmount, string $couponId): array
    {
        $coupon = $this->couponRepository->getCouponById($couponId);

        if (!$coupon) {
            throw new \Exception(SubscriptionMessages::COUPON_INVALID);
        }

        $discount = 0;
        if ($coupon->discount_type === 'percentage') {
            $discount = (int) ($originalAmount * ($coupon->discount_value / 100));
        } else {
            $discount = min($coupon->discount_value, $originalAmount);
        }

        return [
            'original_amount' => $originalAmount,
            'discount_amount' => $discount,
            'final_amount' => $originalAmount - $discount,
            'coupon' => $coupon,
        ];
    }
}
