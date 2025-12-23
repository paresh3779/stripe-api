<?php

declare(strict_types=1);

namespace App\Services\Stripe\Subscription;

use App\Constants\SubscriptionMessages;
use App\Models\User;
use App\Repositories\Stripe\SubscriptionRepository;
use App\Repositories\Stripe\StripeCustomerRepository;

/**
 * Service for subscription checkout with trial period
 * Demo 2: Product with trial period
 */
class SubscriptionTrialCheckoutService extends BaseSubscriptionCheckoutService
{
    public function __construct(
        SubscriptionRepository $subscriptionRepository,
        StripeCustomerRepository $customerRepository
    ) {
        parent::__construct($subscriptionRepository, $customerRepository);
    }

    /**
     * Get subscription products that have trial periods available
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getProductsWithTrial(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->subscriptionRepository->getSubscriptionProductsWithTrial();
    }

    /**
     * Create a subscription checkout session with trial period
     *
     * @param string $priceId The Stripe price ID for the subscription
     * @param User|null $user The authenticated user
     * @return array Contains sessionId and redirect URL
     * @throws \Exception
     */
    public function createCheckoutSession(string $priceId, ?User $user = null): array
    {
        $price = $this->validateRecurringPrice($priceId);

        if (!$price->trial_days || $price->trial_days <= 0) {
            throw new \Exception(SubscriptionMessages::TRIAL_NOT_AVAILABLE);
        }

        $sessionConfig = $this->buildBaseSessionConfig(
            $price->stripe_price_id,
            '/main/stripe-subscription-checkout/trial/success',
            '/main/stripe-subscription-checkout/trial'
        );

        $sessionConfig['subscription_data'] = [
            'trial_period_days' => $price->trial_days,
        ];

        $sessionConfig['metadata'] = [
            'product_id' => $price->product_id,
            'price_id' => $price->id,
            'product_name' => $price->product->name,
            'billing_interval' => $price->interval,
            'trial_days' => $price->trial_days,
            'has_trial' => true,
        ];

        if ($user) {
            $stripeCustomerId = $this->getOrCreateStripeCustomer($user);
            $sessionConfig['customer'] = $stripeCustomerId;
            $sessionConfig['metadata']['user_id'] = $user->id;
        }

        return $this->createSession($sessionConfig);
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
            'has_trial' => $price->trial_days > 0,
            'trial_days' => $price->trial_days ?? 0,
            'price' => $price,
        ];
    }
}
