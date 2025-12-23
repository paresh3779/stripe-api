<?php

declare(strict_types=1);

namespace App\Services\Stripe\SubscriptionPaymentIntent;

use App\Constants\SubscriptionPaymentIntentMessages;
use App\Constants\PaymentStatus;
use App\Models\User;
use App\Repositories\Stripe\SubscriptionRepository;
use App\Repositories\Stripe\StripeCustomerRepository;
use App\Repositories\Stripe\PaymentRepository;
use Stripe\Subscription;

/**
 * Service for basic subscription PaymentIntent (monthly/yearly billing)
 * Demo 1: Product with monthly and yearly subscription
 */
class SubscriptionPaymentIntentService extends BaseSubscriptionPaymentIntentService
{
    public function __construct(
        SubscriptionRepository $subscriptionRepository,
        StripeCustomerRepository $customerRepository,
        PaymentRepository $paymentRepository
    ) {
        parent::__construct($subscriptionRepository, $customerRepository, $paymentRepository);
    }

    /**
     * Create a subscription with PaymentIntent
     *
     * @param string $priceId The price ID for the subscription
     * @param string $paymentMethodId The Stripe payment method ID
     * @param User $user The authenticated user
     * @return array Contains subscription details and client secret
     * @throws \Exception
     */
    public function createSubscription(string $priceId, string $paymentMethodId, User $user): array
    {
        $price = $this->validateRecurringPrice($priceId);
        $product = $price->product;

        $stripeCustomerId = $this->getOrCreateStripeCustomer($user);

        $this->attachPaymentMethodToCustomer($paymentMethodId, $stripeCustomerId);

        $subscription = $this->createStripeSubscription([
            'customer' => $stripeCustomerId,
            'items' => [
                ['price' => $price->stripe_price_id],
            ],
            'default_payment_method' => $paymentMethodId,
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
                'billing_interval' => $price->interval,
            ],
        ]);

        $payment = $this->paymentRepository->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'price_id' => $price->id,
            'stripe_payment_intent_id' => $subscription->id,
            'stripe_subscription_id' => $subscription->id,
            'amount' => $price->amount,
            'currency' => $price->currency,
            'status' => PaymentStatus::PENDING,
            'billing_reason' => 'subscription_create',
            'description' => $product->name . ' - ' . ucfirst($price->interval) . 'ly Subscription',
        ]);

        $clientSecret = null;
        if ($subscription->latest_invoice && $subscription->latest_invoice->payment_intent) {
            $clientSecret = $subscription->latest_invoice->payment_intent->client_secret;
        }

        return [
            'subscriptionId' => $subscription->id,
            'clientSecret' => $clientSecret,
            'status' => $subscription->status,
            'amount' => $price->amount,
            'currency' => $price->currency,
            'interval' => $price->interval,
            'paymentId' => $payment->id,
        ];
    }

    /**
     * Create a SetupIntent for collecting payment method before subscription
     *
     * @param User $user
     * @param string $priceId
     * @return array
     * @throws \Exception
     */
    public function createSetupIntentForSubscription(User $user, string $priceId): array
    {
        $price = $this->validateRecurringPrice($priceId);
        $stripeCustomerId = $this->getOrCreateStripeCustomer($user);

        $setupIntent = $this->createSetupIntent($stripeCustomerId, [
            'user_id' => $user->id,
            'price_id' => $price->id,
            'product_id' => $price->product_id,
        ]);

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
        ];
    }
}
