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

        // Generate idempotency key to prevent duplicate payment intents
        $idempotencyKey = $this->generateIdempotencyKey($user->id, $priceId);

        // Check for existing pending payment intent for same user/price
        $existingPayment = $this->paymentRepository->findPendingPayment(
            $user->id,
            $priceId
        );

        if ($existingPayment && $existingPayment->stripe_payment_intent_id) {
            // Return existing payment intent if it's still valid
            try {
                $existingIntent = \Stripe\PaymentIntent::retrieve($existingPayment->stripe_payment_intent_id);
                
                if (in_array($existingIntent->status, ['requires_payment_method', 'requires_confirmation', 'requires_action'])) {
                    \Log::info('PaymentIntentService: Returning existing payment intent', [
                        'payment_intent_id' => $existingIntent->id,
                        'status' => $existingIntent->status,
                    ]);

                    return [
                        'clientSecret' => $existingIntent->client_secret,
                        'paymentIntentId' => $existingIntent->id,
                        'amount' => $price->amount,
                        'currency' => $price->currency,
                        'paymentId' => $existingPayment->id,
                        'isExisting' => true,
                    ];
                }
            } catch (\Exception $e) {
                \Log::warning('PaymentIntentService: Could not retrieve existing payment intent', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $paymentIntent = $this->createStripePaymentIntent([
            'amount' => $price->amount,
            'currency' => $price->currency,
            'customer' => $stripeCustomerId,
            'description' => $product->name . ' - ' . ($price->description ?? 'One-time purchase'),
            'metadata' => [
                'user_id' => (string) $user->id,
                'product_id' => (string) $product->id,
                'price_id' => (string) $price->id,
                'product_name' => $product->name,
                'idempotency_key' => $idempotencyKey,
            ],
            'automatic_payment_methods' => [
                'enabled' => true,
            ],
        ], $idempotencyKey);

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

        \Log::info('PaymentIntentService: Payment intent created', [
            'payment_intent_id' => $paymentIntent->id,
            'payment_id' => $payment->id,
            'user_id' => $user->id,
            'amount' => $price->amount,
        ]);

        return [
            'clientSecret' => $paymentIntent->client_secret,
            'paymentIntentId' => $paymentIntent->id,
            'amount' => $price->amount,
            'currency' => $price->currency,
            'paymentId' => $payment->id,
            'isExisting' => false,
        ];
    }

    /**
     * Cancel a payment intent
     */
    public function cancelPaymentIntent(string $paymentIntentId, User $user): array
    {
        try {
            $payment = $this->paymentRepository->findByPaymentIntentId($paymentIntentId);

            if (!$payment) {
                throw new \Exception(Messages::PAYMENT_RECORD_NOT_FOUND);
            }

            // Verify the payment belongs to the user
            if ($payment->user_id !== $user->id) {
                throw new \Exception('Unauthorized to cancel this payment');
            }

            $paymentIntent = \Stripe\PaymentIntent::retrieve($paymentIntentId);

            if ($paymentIntent->status === 'succeeded') {
                throw new \Exception('Cannot cancel a completed payment');
            }

            if ($paymentIntent->status !== 'canceled') {
                $paymentIntent->cancel();
            }

            $this->paymentRepository->update($payment, [
                'status' => PaymentStatus::CANCELLED,
            ]);

            \Log::info('PaymentIntentService: Payment intent canceled', [
                'payment_intent_id' => $paymentIntentId,
                'payment_id' => $payment->id,
            ]);

            return [
                'success' => true,
                'message' => 'Payment canceled successfully',
            ];
        } catch (\Stripe\Exception\ApiErrorException $e) {
            \Log::error('PaymentIntentService: Error canceling payment intent', [
                'payment_intent_id' => $paymentIntentId,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Failed to cancel payment: ' . $e->getMessage());
        }
    }
}
