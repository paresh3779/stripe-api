<?php

declare(strict_types=1);

namespace App\Services\Stripe\SubscriptionPaymentIntent;

use App\Constants\SubscriptionPaymentIntentMessages;
use App\Constants\PaymentStatus;
use App\Models\User;
use App\Repositories\Stripe\SubscriptionRepository;
use App\Repositories\Stripe\StripeCustomerRepository;
use App\Repositories\Stripe\PaymentRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * Service for subscription PaymentIntent with trial period
 * Demo 2: Product with trial period
 */
class SubscriptionTrialPaymentIntentService extends BaseSubscriptionPaymentIntentService
{
    public function __construct(
        SubscriptionRepository $subscriptionRepository,
        StripeCustomerRepository $customerRepository,
        PaymentRepository $paymentRepository
    ) {
        parent::__construct($subscriptionRepository, $customerRepository, $paymentRepository);
    }

    /**
     * Get subscription products that have trial periods available
     *
     * @return Collection
     */
    public function getProductsWithTrial(): Collection
    {
        return $this->subscriptionRepository->getSubscriptionProductsWithTrial();
    }

    /**
     * Get trial information for a specific price
     *
     * @param string $priceId
     * @return array
     * @throws \Exception
     */
    public function getTrialInfo(string $priceId): array
    {
        $price = $this->validateRecurringPrice($priceId);

        return [
            'has_trial' => ($price->trial_days ?? 0) > 0,
            'trial_days' => $price->trial_days ?? 0,
            'price' => $price,
        ];
    }

    /**
     * Create a subscription with trial period
     *
     * @param string $priceId The price ID for the subscription
     * @param string $paymentMethodId The Stripe payment method ID
     * @param User $user The authenticated user
     * @return array Contains subscription details
     * @throws \Exception
     */
    public function createSubscriptionWithTrial(string $priceId, string $paymentMethodId, User $user): array
    {
        $price = $this->validateRecurringPrice($priceId);
        $product = $price->product;

        if (!$price->trial_days || $price->trial_days <= 0) {
            throw new \Exception(SubscriptionPaymentIntentMessages::TRIAL_NOT_AVAILABLE);
        }

        $stripeCustomerId = $this->getOrCreateStripeCustomer($user);

        $this->attachPaymentMethodToCustomer($paymentMethodId, $stripeCustomerId);

        $subscription = $this->createStripeSubscription([
            'customer' => $stripeCustomerId,
            'items' => [
                ['price' => $price->stripe_price_id],
            ],
            'default_payment_method' => $paymentMethodId,
            'trial_period_days' => $price->trial_days,
            'payment_settings' => [
                'save_default_payment_method' => 'on_subscription',
            ],
            'metadata' => [
                'user_id' => $user->id,
                'product_id' => $product->id,
                'price_id' => $price->id,
                'product_name' => $product->name,
                'billing_interval' => $price->interval,
                'has_trial' => true,
                'trial_days' => $price->trial_days,
            ],
        ]);

        $payment = $this->paymentRepository->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'price_id' => $price->id,
            'stripe_payment_intent_id' => $subscription->id,
            'stripe_subscription_id' => $subscription->id,
            'amount' => 0,
            'currency' => $price->currency,
            'status' => PaymentStatus::PENDING,
            'billing_reason' => 'subscription_trial',
            'description' => $product->name . ' - ' . $price->trial_days . ' Day Trial',
        ]);

        return [
            'subscriptionId' => $subscription->id,
            'status' => $subscription->status,
            'trialEnd' => $subscription->trial_end,
            'trialDays' => $price->trial_days,
            'amount' => $price->amount,
            'currency' => $price->currency,
            'interval' => $price->interval,
            'paymentId' => $payment->id,
        ];
    }

    /**
     * Create a SetupIntent for trial subscription (no immediate payment)
     *
     * @param User $user
     * @param string $priceId
     * @return array
     * @throws \Exception
     */
    public function createSetupIntentForTrial(User $user, string $priceId): array
    {
        $price = $this->validateRecurringPrice($priceId);

        if (!$price->trial_days || $price->trial_days <= 0) {
            throw new \Exception(SubscriptionPaymentIntentMessages::TRIAL_NOT_AVAILABLE);
        }

        $stripeCustomerId = $this->getOrCreateStripeCustomer($user);

        $setupIntent = $this->createSetupIntent($stripeCustomerId, [
            'user_id' => $user->id,
            'price_id' => $price->id,
            'product_id' => $price->product_id,
            'has_trial' => true,
            'trial_days' => $price->trial_days,
        ]);

        return [
            'clientSecret' => $setupIntent->client_secret,
            'setupIntentId' => $setupIntent->id,
            'customerId' => $stripeCustomerId,
            'trialDays' => $price->trial_days,
            'price' => [
                'id' => $price->id,
                'amount' => $price->amount,
                'currency' => $price->currency,
                'interval' => $price->interval,
            ],
        ];
    }
}
