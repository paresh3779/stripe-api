<?php

declare(strict_types=1);

namespace App\Services\Stripe\Subscription;

use App\Constants\SubscriptionMessages;
use App\Models\User;
use App\Repositories\Stripe\SubscriptionRepository;
use App\Repositories\Stripe\StripeCustomerRepository;
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\Checkout\Session;

/**
 * Base service for Stripe subscription checkout operations
 * Provides common functionality for all subscription checkout services
 */
abstract class BaseSubscriptionCheckoutService
{
    protected SubscriptionRepository $subscriptionRepository;
    protected StripeCustomerRepository $customerRepository;

    public function __construct(
        SubscriptionRepository $subscriptionRepository,
        StripeCustomerRepository $customerRepository
    ) {
        $this->subscriptionRepository = $subscriptionRepository;
        $this->customerRepository = $customerRepository;
        Stripe::setApiKey(config('stripe.secret'));
    }

    /**
     * Get all subscription products
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getProducts(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->subscriptionRepository->getSubscriptionProducts();
    }

    /**
     * Get a single subscription product with prices
     *
     * @param string $productId
     * @return object
     * @throws \Exception
     */
    public function getProductWithPrices(string $productId): object
    {
        $product = $this->subscriptionRepository->getSubscriptionProductById($productId);

        if (!$product) {
            throw new \Exception(SubscriptionMessages::PRODUCT_NOT_FOUND);
        }

        return $product;
    }

    /**
     * Get or create a Stripe customer for the user
     *
     * @param User $user
     * @return string Stripe customer ID
     * @throws \Exception
     */
    protected function getOrCreateStripeCustomer(User $user): string
    {
        $stripeCustomer = $this->customerRepository->findByUserId($user->id);

        if ($stripeCustomer) {
            return $stripeCustomer->stripe_customer_id;
        }

        try {
            $customer = Customer::create([
                'email' => $user->email,
                'name' => $user->first_name . ' ' . $user->last_name,
                'metadata' => [
                    'user_id' => $user->id,
                ],
            ]);

            $this->customerRepository->create(
                $user->id,
                $customer->id,
                $user->email
            );

            return $customer->id;
        } catch (\Exception $e) {
            throw new \Exception(SubscriptionMessages::CUSTOMER_CREATION_FAILED);
        }
    }

    /**
     * Build base checkout session configuration
     *
     * @param string $priceId
     * @param string $successPath
     * @param string $cancelPath
     * @return array
     */
    protected function buildBaseSessionConfig(string $priceId, string $successPath, string $cancelPath): array
    {
        return [
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price' => $priceId,
                'quantity' => 1,
            ]],
            'mode' => 'subscription',
            'success_url' => config('app.frontend_url') . $successPath . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => config('app.frontend_url') . $cancelPath,
        ];
    }

    /**
     * Create Stripe checkout session
     *
     * @param array $sessionConfig
     * @return array
     * @throws \Exception
     */
    protected function createSession(array $sessionConfig): array
    {
        try {
            $session = Session::create($sessionConfig);

            return [
                'sessionId' => $session->id,
                'url' => $session->url,
            ];
        } catch (\Exception $e) {
            throw new \Exception(SubscriptionMessages::CHECKOUT_SESSION_FAILED . ': ' . $e->getMessage());
        }
    }

    /**
     * Validate that the price is a valid recurring price
     *
     * @param string $priceId
     * @return \App\Models\Price
     * @throws \Exception
     */
    protected function validateRecurringPrice(string $priceId): \App\Models\Price
    {
        $price = $this->subscriptionRepository->getRecurringPriceById($priceId);

        if (!$price) {
            throw new \Exception(SubscriptionMessages::PRICE_NOT_FOUND);
        }

        if ($price->type !== 'recurring') {
            throw new \Exception(SubscriptionMessages::PRICE_NOT_RECURRING);
        }

        return $price;
    }
}
