<?php

declare(strict_types=1);

namespace App\Services\Stripe\Subscription;

use App\Constants\SubscriptionMessages;
use App\Models\User;
use App\Repositories\Stripe\SubscriptionRepository;
use App\Repositories\Stripe\StripeCustomerRepository;

/**
 * Service for basic subscription checkout (monthly/yearly billing)
 * Demo 1: Product with monthly and yearly subscription
 */
class SubscriptionCheckoutService extends BaseSubscriptionCheckoutService
{
    public function __construct(
        SubscriptionRepository $subscriptionRepository,
        StripeCustomerRepository $customerRepository
    ) {
        parent::__construct($subscriptionRepository, $customerRepository);
    }

    /**
     * Create a subscription checkout session
     *
     * @param string $priceId The Stripe price ID for the subscription
     * @param User|null $user The authenticated user
     * @return array Contains sessionId and redirect URL
     * @throws \Exception
     */
    public function createCheckoutSession(string $priceId, ?User $user = null): array
    {
        $price = $this->validateRecurringPrice($priceId);

        $sessionConfig = $this->buildBaseSessionConfig(
            $price->stripe_price_id,
            '/main/stripe-subscription-checkout/subscription/success',
            '/main/stripe-subscription-checkout/subscription'
        );

        $sessionConfig['metadata'] = [
            'product_id' => $price->product_id,
            'price_id' => $price->id,
            'product_name' => $price->product->name,
            'billing_interval' => $price->interval,
        ];

        if ($user) {
            $stripeCustomerId = $this->getOrCreateStripeCustomer($user);
            $sessionConfig['customer'] = $stripeCustomerId;
            $sessionConfig['metadata']['user_id'] = $user->id;
        }

        return $this->createSession($sessionConfig);
    }

    /**
     * Get products with both monthly and yearly pricing options
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getProductsWithBillingOptions(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->subscriptionRepository->getSubscriptionProducts();
    }
}
