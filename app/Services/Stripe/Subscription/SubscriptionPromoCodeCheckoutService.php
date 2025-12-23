<?php

declare(strict_types=1);

namespace App\Services\Stripe\Subscription;

use App\Constants\SubscriptionMessages;
use App\Models\User;
use App\Repositories\Stripe\SubscriptionRepository;
use App\Repositories\Stripe\StripeCustomerRepository;
use App\Repositories\Stripe\PromoCodeRepository;

/**
 * Service for subscription checkout with promo code discount
 * Demo 4: Product with promocode
 */
class SubscriptionPromoCodeCheckoutService extends BaseSubscriptionCheckoutService
{
    protected PromoCodeRepository $promoCodeRepository;

    public function __construct(
        SubscriptionRepository $subscriptionRepository,
        StripeCustomerRepository $customerRepository,
        PromoCodeRepository $promoCodeRepository
    ) {
        parent::__construct($subscriptionRepository, $customerRepository);
        $this->promoCodeRepository = $promoCodeRepository;
    }

    /**
     * Validate a promo code and return its details
     *
     * @param string $code
     * @return array
     * @throws \Exception
     */
    public function validatePromoCode(string $code): array
    {
        $promoCode = $this->promoCodeRepository->getActivePromoCodeByCode($code);

        if (!$promoCode) {
            throw new \Exception(SubscriptionMessages::PROMO_CODE_INVALID);
        }

        if (!$promoCode->active) {
            throw new \Exception(SubscriptionMessages::PROMO_CODE_NOT_ACTIVE);
        }

        if (!$promoCode->coupon || !$promoCode->coupon->active) {
            throw new \Exception(SubscriptionMessages::COUPON_NOT_ACTIVE);
        }

        return [
            'valid' => true,
            'promoCode' => $promoCode,
            'coupon' => $promoCode->coupon,
        ];
    }

    /**
     * Create a subscription checkout session with promo code applied
     *
     * @param string $priceId The Stripe price ID for the subscription
     * @param string $promoCode The promo code to apply
     * @param User|null $user The authenticated user
     * @return array Contains sessionId and redirect URL
     * @throws \Exception
     */
    public function createCheckoutSession(string $priceId, string $promoCode, ?User $user = null): array
    {
        $price = $this->validateRecurringPrice($priceId);
        
        $promoCodeModel = $this->promoCodeRepository->getActivePromoCodeByCode($promoCode);
        if (!$promoCodeModel) {
            throw new \Exception(SubscriptionMessages::PROMO_CODE_INVALID);
        }

        $sessionConfig = $this->buildBaseSessionConfig(
            $price->stripe_price_id,
            '/main/stripe-subscription-checkout/promocode/success',
            '/main/stripe-subscription-checkout/promocode'
        );

        $sessionConfig['discounts'] = [[
            'promotion_code' => $promoCodeModel->stripe_promotion_code_id,
        ]];

        $sessionConfig['metadata'] = [
            'product_id' => $price->product_id,
            'price_id' => $price->id,
            'product_name' => $price->product->name,
            'billing_interval' => $price->interval,
            'promo_code_id' => $promoCodeModel->id,
            'promo_code' => $promoCodeModel->code,
        ];

        if ($user) {
            $stripeCustomerId = $this->getOrCreateStripeCustomer($user);
            $sessionConfig['customer'] = $stripeCustomerId;
            $sessionConfig['metadata']['user_id'] = $user->id;
        }

        return $this->createSession($sessionConfig);
    }

    /**
     * Calculate discounted price based on promo code
     *
     * @param int $originalAmount Amount in cents
     * @param string $code
     * @return array
     * @throws \Exception
     */
    public function calculateDiscount(int $originalAmount, string $code): array
    {
        $promoCode = $this->promoCodeRepository->getActivePromoCodeByCode($code);

        if (!$promoCode || !$promoCode->coupon) {
            throw new \Exception(SubscriptionMessages::PROMO_CODE_INVALID);
        }

        $coupon = $promoCode->coupon;
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
            'promo_code' => $promoCode,
            'coupon' => $coupon,
        ];
    }
}
