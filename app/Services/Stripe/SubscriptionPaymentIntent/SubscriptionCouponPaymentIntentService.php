<?php

declare(strict_types=1);

namespace App\Services\Stripe\SubscriptionPaymentIntent;

use App\Constants\SubscriptionPaymentIntentMessages;
use App\Constants\PaymentStatus;
use App\Models\User;
use App\Repositories\Stripe\SubscriptionRepository;
use App\Repositories\Stripe\StripeCustomerRepository;
use App\Repositories\Stripe\PaymentRepository;
use App\Repositories\Stripe\CouponRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * Service for subscription PaymentIntent with coupon discount
 * Demo 3: Product with coupon
 */
class SubscriptionCouponPaymentIntentService extends BaseSubscriptionPaymentIntentService
{
    protected CouponRepository $couponRepository;

    public function __construct(
        SubscriptionRepository $subscriptionRepository,
        StripeCustomerRepository $customerRepository,
        PaymentRepository $paymentRepository,
        CouponRepository $couponRepository
    ) {
        parent::__construct($subscriptionRepository, $customerRepository, $paymentRepository);
        $this->couponRepository = $couponRepository;
    }

    /**
     * Get all active coupons applicable for subscriptions
     *
     * @return Collection
     */
    public function getCoupons(): Collection
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
            throw new \Exception(SubscriptionPaymentIntentMessages::COUPON_INVALID);
        }

        if (!$coupon->active) {
            throw new \Exception(SubscriptionPaymentIntentMessages::COUPON_NOT_ACTIVE);
        }

        return [
            'valid' => true,
            'coupon' => $coupon,
        ];
    }

    /**
     * Calculate discounted price based on coupon
     *
     * @param int $originalAmount Amount in cents
     * @param string $couponId
     * @return array
     * @throws \Exception
     */
    public function calculateCouponDiscount(int $originalAmount, string $couponId): array
    {
        $coupon = $this->couponRepository->getCouponById($couponId);

        if (!$coupon) {
            throw new \Exception(SubscriptionPaymentIntentMessages::COUPON_INVALID);
        }

        $discount = $this->calculateDiscount($originalAmount, $coupon->discount_type, $coupon->discount_value);

        return [
            'original_amount' => $originalAmount,
            'discount_amount' => $discount,
            'final_amount' => $originalAmount - $discount,
            'coupon' => $coupon,
        ];
    }

    /**
     * Create a subscription with coupon applied
     *
     * @param string $priceId The price ID for the subscription
     * @param string $paymentMethodId The Stripe payment method ID
     * @param string $couponId The coupon ID to apply
     * @param User $user The authenticated user
     * @return array Contains subscription details
     * @throws \Exception
     */
    public function createSubscriptionWithCoupon(string $priceId, string $paymentMethodId, string $couponId, User $user): array
    {
        $price = $this->validateRecurringPrice($priceId);
        $product = $price->product;

        $coupon = $this->couponRepository->getCouponById($couponId);
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
            'coupon' => $coupon->stripe_coupon_id,
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
                'coupon_id' => $coupon->id,
                'coupon_code' => $coupon->name,
            ],
        ]);

        $this->couponRepository->incrementRedemptions($coupon);

        $payment = $this->paymentRepository->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'price_id' => $price->id,
            'coupon_id' => $coupon->id,
            'stripe_payment_intent_id' => $subscription->id,
            'stripe_subscription_id' => $subscription->id,
            'amount' => $finalAmount,
            'currency' => $price->currency,
            'status' => PaymentStatus::PENDING,
            'billing_reason' => 'subscription_create',
            'description' => $product->name . ' with Coupon: ' . $coupon->name,
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
            'coupon' => $coupon,
            'paymentId' => $payment->id,
        ];
    }

    /**
     * Create a SetupIntent for subscription with coupon
     *
     * @param User $user
     * @param string $priceId
     * @param string $couponId
     * @return array
     * @throws \Exception
     */
    public function createSetupIntentWithCoupon(User $user, string $priceId, string $couponId): array
    {
        $price = $this->validateRecurringPrice($priceId);
        $coupon = $this->couponRepository->getCouponById($couponId);

        if (!$coupon) {
            throw new \Exception(SubscriptionPaymentIntentMessages::COUPON_INVALID);
        }

        $stripeCustomerId = $this->getOrCreateStripeCustomer($user);

        $setupIntent = $this->createSetupIntent($stripeCustomerId, [
            'user_id' => $user->id,
            'price_id' => $price->id,
            'product_id' => $price->product_id,
            'coupon_id' => $coupon->id,
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
            'coupon' => $coupon,
        ];
    }
}
