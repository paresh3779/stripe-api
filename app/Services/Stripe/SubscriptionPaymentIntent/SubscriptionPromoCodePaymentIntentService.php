<?php

declare(strict_types=1);

namespace App\Services\Stripe\SubscriptionPaymentIntent;

use App\Constants\SubscriptionPaymentIntentMessages;
use App\Constants\PaymentStatus;
use App\Models\User;
use App\Repositories\Stripe\SubscriptionRepository;
use App\Repositories\Stripe\StripeCustomerRepository;
use App\Repositories\Stripe\PaymentRepository;
use App\Repositories\Stripe\PromoCodeRepository;

/**
 * Service for subscription PaymentIntent with promo code discount
 * Demo 4: Product with promocode
 */
class SubscriptionPromoCodePaymentIntentService extends BaseSubscriptionPaymentIntentService
{
    protected PromoCodeRepository $promoCodeRepository;

    public function __construct(
        SubscriptionRepository $subscriptionRepository,
        StripeCustomerRepository $customerRepository,
        PaymentRepository $paymentRepository,
        PromoCodeRepository $promoCodeRepository
    ) {
        parent::__construct($subscriptionRepository, $customerRepository, $paymentRepository);
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
            throw new \Exception(SubscriptionPaymentIntentMessages::PROMO_CODE_INVALID);
        }

        if (!$promoCode->active) {
            throw new \Exception(SubscriptionPaymentIntentMessages::PROMO_CODE_NOT_ACTIVE);
        }

        if (!$promoCode->coupon || !$promoCode->coupon->active) {
            throw new \Exception(SubscriptionPaymentIntentMessages::COUPON_NOT_ACTIVE);
        }

        return [
            'valid' => true,
            'promoCode' => $promoCode,
            'coupon' => $promoCode->coupon,
        ];
    }

    /**
     * Calculate discounted price based on promo code
     *
     * @param int $originalAmount Amount in cents
     * @param string $code
     * @return array
     * @throws \Exception
     */
    public function calculatePromoCodeDiscount(int $originalAmount, string $code): array
    {
        $promoCode = $this->promoCodeRepository->getActivePromoCodeByCode($code);

        if (!$promoCode || !$promoCode->coupon) {
            throw new \Exception(SubscriptionPaymentIntentMessages::PROMO_CODE_INVALID);
        }

        $coupon = $promoCode->coupon;
        $discount = $this->calculateDiscount($originalAmount, $coupon->discount_type, $coupon->discount_value);

        return [
            'original_amount' => $originalAmount,
            'discount_amount' => $discount,
            'final_amount' => $originalAmount - $discount,
            'promo_code' => $promoCode,
            'coupon' => $coupon,
        ];
    }

    /**
     * Create a subscription with promo code applied
     *
     * @param string $priceId The price ID for the subscription
     * @param string $paymentMethodId The Stripe payment method ID
     * @param string $promoCode The promo code to apply
     * @param User $user The authenticated user
     * @return array Contains subscription details
     * @throws \Exception
     */
    public function createSubscriptionWithPromoCode(string $priceId, string $paymentMethodId, string $promoCode, User $user): array
    {
        $price = $this->validateRecurringPrice($priceId);
        $product = $price->product;

        $promoCodeModel = $this->promoCodeRepository->getActivePromoCodeByCode($promoCode);
        if (!$promoCodeModel) {
            throw new \Exception(SubscriptionPaymentIntentMessages::PROMO_CODE_INVALID);
        }

        $coupon = $promoCodeModel->coupon;
        if (!$coupon) {
            throw new \Exception(SubscriptionPaymentIntentMessages::COUPON_INVALID);
        }

        $stripeCustomerId = $this->getOrCreateStripeCustomer($user);

        $this->attachPaymentMethodToCustomer($paymentMethodId, $stripeCustomerId);

        $discountAmount = $this->calculateDiscount($price->amount, $coupon->discount_type, $coupon->discount_value);
        $finalAmount = $price->amount - $discountAmount;

        $subscription = $this->createStripeSubscription([
            'customer' => $stripeCustomerId,
            'items' => [
                ['price' => $price->stripe_price_id],
            ],
            'default_payment_method' => $paymentMethodId,
            'promotion_code' => $promoCodeModel->stripe_promotion_code_id,
            'payment_behavior' => 'default_incomplete',
            'payment_settings' => [
                'save_default_payment_method' => 'on_subscription',
            ],
            'expand' => ['latest_invoice.payment_intent'],
            'metadata' => [
                'user_id' => $user->id,
                'product_id' => $product->id,
                'price_id' => $price->id,
                'product_name' => $product->name,
                'promo_code_id' => $promoCodeModel->id,
                'promo_code' => $promoCodeModel->code,
            ],
        ]);

        $this->promoCodeRepository->incrementRedemptions($promoCodeModel);

        $payment = $this->paymentRepository->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'price_id' => $price->id,
            'promo_code_id' => $promoCodeModel->id,
            'stripe_payment_intent_id' => $subscription->id,
            'stripe_subscription_id' => $subscription->id,
            'amount' => $finalAmount,
            'currency' => $price->currency,
            'status' => PaymentStatus::PENDING,
            'billing_reason' => 'subscription_create',
            'description' => $product->name . ' with Promo: ' . $promoCodeModel->code,
        ]);

        $clientSecret = null;
        if ($subscription->latest_invoice && $subscription->latest_invoice->payment_intent) {
            $clientSecret = $subscription->latest_invoice->payment_intent->client_secret;
        }

        return [
            'subscriptionId' => $subscription->id,
            'clientSecret' => $clientSecret,
            'status' => $subscription->status,
            'originalAmount' => $price->amount,
            'discountAmount' => $discountAmount,
            'finalAmount' => $finalAmount,
            'currency' => $price->currency,
            'interval' => $price->interval,
            'promoCode' => $promoCodeModel,
            'paymentId' => $payment->id,
        ];
    }

    /**
     * Create a SetupIntent for subscription with promo code
     *
     * @param User $user
     * @param string $priceId
     * @param string $promoCode
     * @return array
     * @throws \Exception
     */
    public function createSetupIntentWithPromoCode(User $user, string $priceId, string $promoCode): array
    {
        $price = $this->validateRecurringPrice($priceId);
        $promoCodeModel = $this->promoCodeRepository->getActivePromoCodeByCode($promoCode);

        if (!$promoCodeModel) {
            throw new \Exception(SubscriptionPaymentIntentMessages::PROMO_CODE_INVALID);
        }

        $coupon = $promoCodeModel->coupon;
        if (!$coupon) {
            throw new \Exception(SubscriptionPaymentIntentMessages::COUPON_INVALID);
        }

        $stripeCustomerId = $this->getOrCreateStripeCustomer($user);

        $setupIntent = $this->createSetupIntent($stripeCustomerId, [
            'user_id' => $user->id,
            'price_id' => $price->id,
            'product_id' => $price->product_id,
            'promo_code_id' => $promoCodeModel->id,
        ]);

        $discountAmount = $this->calculateDiscount($price->amount, $coupon->discount_type, $coupon->discount_value);

        return [
            'clientSecret' => $setupIntent->client_secret,
            'setupIntentId' => $setupIntent->id,
            'customerId' => $stripeCustomerId,
            'price' => [
                'id' => $price->id,
                'amount' => $price->amount,
                'currency' => $price->currency,
                'interval' => $price->interval,
            ],
            'discount' => [
                'original_amount' => $price->amount,
                'discount_amount' => $discountAmount,
                'final_amount' => $price->amount - $discountAmount,
            ],
            'promoCode' => $promoCodeModel,
            'coupon' => $coupon,
        ];
    }
}
