<?php

namespace App\Services\Stripe;

use App\Repositories\Stripe\PromoCodeRepository;
use App\Models\User;
use App\Constants\Messages;
use App\Constants\PaymentStatus;
use Stripe\PaymentIntent;

class PaymentIntentWithPromoCodeService extends BasePaymentIntentService
{
    public function __construct(
        protected readonly PromoCodeRepository $promoCodeRepository,
        ...$dependencies
    ) {
        parent::__construct(...$dependencies);
    }

    /**
     * Validate promo code
     *
     * @param string $code
     * @param string $priceId
     * @return array
     * @throws \Exception
     */
    public function validatePromoCode(string $code, string $priceId): array
    {
        $promoCode = $this->promoCodeRepository->findByCode($code);

        if (!$promoCode) {
            throw new \Exception(Messages::PROMO_CODE_INVALID);
        }

        if (!$promoCode->active) {
            throw new \Exception(Messages::PROMO_CODE_NOT_ACTIVE);
        }

        if ($promoCode->valid_from && now()->lt($promoCode->valid_from)) {
            throw new \Exception(Messages::PROMO_CODE_NOT_YET_VALID);
        }

        if ($promoCode->valid_until && now()->gt($promoCode->valid_until)) {
            throw new \Exception(Messages::PROMO_CODE_EXPIRED);
        }

        if ($promoCode->max_redemptions && $promoCode->times_redeemed >= $promoCode->max_redemptions) {
            throw new \Exception(Messages::PROMO_CODE_MAX_REDEMPTIONS);
        }

        $price = $this->validatePrice($priceId);
        $coupon = $promoCode->coupon;

        if (!$coupon || !$coupon->active) {
            throw new \Exception(Messages::PROMO_CODE_COUPON_INACTIVE);
        }

        $discountAmount = $this->calculateDiscount($price->amount, $coupon->discount_type, $coupon->discount_value);
        $finalAmount = max(0, $price->amount - $discountAmount);

        return [
            'valid' => true,
            'promo_code' => $promoCode,
            'coupon' => $coupon,
            'original_amount' => $price->amount,
            'discount_amount' => $discountAmount,
            'final_amount' => $finalAmount,
            'discount_type' => $coupon->discount_type,
            'discount_value' => $coupon->discount_value,
        ];
    }


    /**
     * Create PaymentIntent with promo code
     *
     * @param string $priceId
     * @param string $promoCode
     * @param User $user
     * @return array
     * @throws \Exception
     */
    public function createPaymentIntent(string $priceId, string $promoCode, User $user): array
    {
        $price = $this->validatePrice($priceId);
        $validation = $this->validatePromoCode($promoCode, $priceId);

        if ($validation['final_amount'] <= 0) {
            throw new \Exception(Messages::INVALID_DISCOUNT_CALCULATION);
        }

        $product = $price->product;
        $stripeCustomerId = $this->getOrCreateStripeCustomer($user);

        $paymentIntent = $this->createStripePaymentIntent([
            'amount' => $validation['final_amount'],
            'currency' => $price->currency,
            'customer' => $stripeCustomerId,
            'description' => $product->name . ' - ' . ($price->description ?? 'One-time purchase') . ' (Promo: ' . $promoCode . ')',
            'metadata' => [
                'user_id' => $user->id,
                'product_id' => $product->id,
                'price_id' => $price->id,
                'product_name' => $product->name,
                'promo_code' => $promoCode,
                'promo_code_id' => $validation['promo_code']->id,
                'coupon_id' => $validation['coupon']->id,
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
            'description' => $product->name . ' (Promo: ' . $promoCode . ')',
        ]);

        return [
            'clientSecret' => $paymentIntent->client_secret,
            'paymentIntentId' => $paymentIntent->id,
            'amount' => $validation['final_amount'],
            'originalAmount' => $validation['original_amount'],
            'discountAmount' => $validation['discount_amount'],
            'currency' => $price->currency,
            'paymentId' => $payment->id,
            'promoCode' => $promoCode,
        ];
    }

    /**
     * Handle successful payment - increment promo code redemptions
     *
     * @param PaymentIntent $paymentIntent
     * @return void
     */
    protected function handleSuccessfulPayment(PaymentIntent $paymentIntent): void
    {
        if (isset($paymentIntent->metadata['promo_code_id'])) {
            $promoCode = $this->promoCodeRepository->findById($paymentIntent->metadata['promo_code_id']);
            if ($promoCode) {
                $this->promoCodeRepository->incrementRedemptions($promoCode);
            }
        }
    }
}
