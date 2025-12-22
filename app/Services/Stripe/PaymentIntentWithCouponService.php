<?php

namespace App\Services\Stripe;

use App\Repositories\Stripe\CouponRepository;
use App\Models\User;
use App\Constants\Messages;
use App\Constants\PaymentStatus;
use Illuminate\Support\Collection;
use Stripe\PaymentIntent;

class PaymentIntentWithCouponService extends BasePaymentIntentService
{
    public function __construct(
        protected readonly CouponRepository $couponRepository,
        ...$dependencies
    ) {
        parent::__construct(...$dependencies);
    }

    /**
     * Get all active coupons
     *
     * @return Collection
     */
    public function getCoupons(): Collection
    {
        return $this->couponRepository->getActiveCoupons();
    }

    /**
     * Validate coupon
     *
     * @param string $couponId
     * @param string $priceId
     * @return array
     * @throws \Exception
     */
    public function validateCoupon(string $couponId, string $priceId): array
    {
        $coupon = $this->couponRepository->findById($couponId);

        if (!$coupon) {
            throw new \Exception(Messages::COUPON_INVALID);
        }

        if (!$coupon->active) {
            throw new \Exception(Messages::COUPON_NOT_ACTIVE);
        }

        if ($coupon->valid_from && now()->lt($coupon->valid_from)) {
            throw new \Exception(Messages::COUPON_NOT_YET_VALID);
        }

        if ($coupon->valid_until && now()->gt($coupon->valid_until)) {
            throw new \Exception(Messages::COUPON_EXPIRED);
        }

        if ($coupon->max_redemptions && $coupon->times_redeemed >= $coupon->max_redemptions) {
            throw new \Exception(Messages::COUPON_MAX_REDEMPTIONS);
        }

        $price = $this->validatePrice($priceId);
        $discountAmount = $this->calculateDiscount($price->amount, $coupon->discount_type, $coupon->discount_value);
        $finalAmount = max(0, $price->amount - $discountAmount);

        return [
            'valid' => true,
            'coupon' => $coupon,
            'original_amount' => $price->amount,
            'discount_amount' => $discountAmount,
            'final_amount' => $finalAmount,
            'discount_type' => $coupon->discount_type,
            'discount_value' => $coupon->discount_value,
        ];
    }


    /**
     * Create PaymentIntent with coupon
     *
     * @param string $priceId
     * @param string $couponId
     * @param User $user
     * @return array
     * @throws \Exception
     */
    public function createPaymentIntent(string $priceId, string $couponId, User $user): array
    {
        $price = $this->validatePrice($priceId);
        $validation = $this->validateCoupon($couponId, $priceId);

        if ($validation['final_amount'] <= 0) {
            throw new \Exception(Messages::INVALID_DISCOUNT_CALCULATION);
        }

        $product = $price->product;
        $stripeCustomerId = $this->getOrCreateStripeCustomer($user);

        $paymentIntent = $this->createStripePaymentIntent([
            'amount' => $validation['final_amount'],
            'currency' => $price->currency,
            'customer' => $stripeCustomerId,
            'description' => $product->name . ' - ' . ($price->description ?? 'One-time purchase') . ' (Coupon: ' . $validation['coupon']->name . ')',
            'metadata' => [
                'user_id' => $user->id,
                'product_id' => $product->id,
                'price_id' => $price->id,
                'product_name' => $product->name,
                'coupon_id' => $validation['coupon']->id,
                'coupon_name' => $validation['coupon']->name,
                'original_amount' => $validation['original_amount'],
                'discount_amount' => $validation['discount_amount'],
            ],
            'automatic_payment_methods' => [
                'enabled' => true,
            ],
        ]);

        $payment = $this->paymentRepository->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'price_id' => $price->id,
            'stripe_payment_intent_id' => $paymentIntent->id,
            'amount' => $validation['final_amount'],
            'currency' => $price->currency,
            'status' => PaymentStatus::PENDING,
            'billing_reason' => 'one_time',
            'description' => $product->name . ' (Coupon: ' . $validation['coupon']->name . ')',
        ]);

        return [
            'clientSecret' => $paymentIntent->client_secret,
            'paymentIntentId' => $paymentIntent->id,
            'amount' => $validation['final_amount'],
            'originalAmount' => $validation['original_amount'],
            'discountAmount' => $validation['discount_amount'],
            'currency' => $price->currency,
            'paymentId' => $payment->id,
            'coupon' => $validation['coupon'],
        ];
    }

    /**
     * Handle successful payment - increment coupon redemptions
     *
     * @param PaymentIntent $paymentIntent
     * @return void
     */
    protected function handleSuccessfulPayment(PaymentIntent $paymentIntent): void
    {
        if (isset($paymentIntent->metadata['coupon_id'])) {
            $coupon = $this->couponRepository->findById($paymentIntent->metadata['coupon_id']);
            if ($coupon) {
                $this->couponRepository->incrementRedemptions($coupon);
            }
        }
    }
}
