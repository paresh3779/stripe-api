<?php

namespace App\Services\Stripe;

use App\Models\User;
use App\Constants\Messages;
use App\Constants\PaymentStatus;

class PaymentIntentService extends BasePaymentIntentService
{

    /**
     * Create PaymentIntent for one-time product
     *
     * @param string $priceId
     * @param User $user
     * @return array
     * @throws \Exception
     */
    public function createPaymentIntent(string $priceId, User $user): array
    {
        $price = $this->validatePrice($priceId);
        $product = $price->product;
        $stripeCustomerId = $this->getOrCreateStripeCustomer($user);

        $paymentIntent = $this->createStripePaymentIntent([
            'amount' => $price->amount,
            'currency' => $price->currency,
            'customer' => $stripeCustomerId,
            'description' => $product->name . ' - ' . ($price->description ?? 'One-time purchase'),
            'metadata' => [
                'user_id' => $user->id,
                'product_id' => $product->id,
                'price_id' => $price->id,
                'product_name' => $product->name,
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
            'amount' => $price->amount,
            'currency' => $price->currency,
            'status' => PaymentStatus::PENDING,
            'billing_reason' => 'one_time',
            'description' => $product->name,
        ]);

        return [
            'clientSecret' => $paymentIntent->client_secret,
            'paymentIntentId' => $paymentIntent->id,
            'amount' => $price->amount,
            'currency' => $price->currency,
            'paymentId' => $payment->id,
        ];
    }

}
