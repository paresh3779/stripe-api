<?php

declare(strict_types=1);

namespace App\Services\Stripe\SubscriptionPaymentIntent;

use App\Constants\SubscriptionPaymentIntentMessages;
use App\Constants\PaymentStatus;
use App\Constants\DiscountType;
use App\Models\User;
use App\Models\Price;
use App\Repositories\Stripe\SubscriptionRepository;
use App\Repositories\Stripe\StripeCustomerRepository;
use App\Repositories\Stripe\PaymentRepository;
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\PaymentIntent;
use Stripe\Subscription;
use Stripe\PaymentMethod;
use Stripe\SetupIntent;
use Stripe\Exception\ApiErrorException;
use Illuminate\Database\Eloquent\Collection;

/**
 * Base service for Stripe subscription PaymentIntent operations
 * Provides common functionality for all subscription payment intent services
 */
abstract class BaseSubscriptionPaymentIntentService
{
    protected SubscriptionRepository $subscriptionRepository;
    protected StripeCustomerRepository $customerRepository;
    protected PaymentRepository $paymentRepository;

    public function __construct(
        SubscriptionRepository $subscriptionRepository,
        StripeCustomerRepository $customerRepository,
        PaymentRepository $paymentRepository
    ) {
        $this->subscriptionRepository = $subscriptionRepository;
        $this->customerRepository = $customerRepository;
        $this->paymentRepository = $paymentRepository;
        Stripe::setApiKey(config('stripe.secret'));
    }

    /**
     * Get all subscription products
     *
     * @return Collection
     */
    public function getProducts(): Collection
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
            throw new \Exception(SubscriptionPaymentIntentMessages::PRODUCT_NOT_FOUND);
        }

        return $product;
    }

    /**
     * Validate that the price is a valid recurring price
     *
     * @param string $priceId
     * @return Price
     * @throws \Exception
     */
    protected function validateRecurringPrice(string $priceId): Price
    {
        $price = $this->subscriptionRepository->getRecurringPriceById($priceId);

        if (!$price) {
            throw new \Exception(SubscriptionPaymentIntentMessages::PRICE_NOT_FOUND);
        }

        if ($price->type !== 'recurring') {
            throw new \Exception(SubscriptionPaymentIntentMessages::PRICE_NOT_RECURRING);
        }

        if (!$price->active) {
            throw new \Exception(SubscriptionPaymentIntentMessages::PRICE_NOT_ACTIVE);
        }

        $product = $price->product;
        if (!$product || !$product->active || $product->is_archived) {
            throw new \Exception(SubscriptionPaymentIntentMessages::PRODUCT_NOT_AVAILABLE);
        }

        return $price;
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
        } catch (ApiErrorException $e) {
            throw new \Exception(SubscriptionPaymentIntentMessages::CUSTOMER_CREATION_FAILED . ': ' . $e->getMessage());
        }
    }

    /**
     * Create a SetupIntent for collecting payment method
     *
     * @param string $customerId
     * @param array $metadata
     * @return SetupIntent
     * @throws \Exception
     */
    protected function createSetupIntent(string $customerId, array $metadata = []): SetupIntent
    {
        try {
            return SetupIntent::create([
                'customer' => $customerId,
                'payment_method_types' => ['card'],
                'metadata' => $metadata,
            ]);
        } catch (ApiErrorException $e) {
            throw new \Exception(SubscriptionPaymentIntentMessages::PAYMENT_INTENT_CREATION_FAILED . ': ' . $e->getMessage());
        }
    }

    /**
     * Attach payment method to customer
     *
     * @param string $paymentMethodId
     * @param string $customerId
     * @return PaymentMethod
     * @throws \Exception
     */
    protected function attachPaymentMethodToCustomer(string $paymentMethodId, string $customerId): PaymentMethod
    {
        try {
            $paymentMethod = PaymentMethod::retrieve($paymentMethodId);
            $paymentMethod->attach(['customer' => $customerId]);

            Customer::update($customerId, [
                'invoice_settings' => [
                    'default_payment_method' => $paymentMethodId,
                ],
            ]);

            return $paymentMethod;
        } catch (ApiErrorException $e) {
            throw new \Exception('Failed to attach payment method: ' . $e->getMessage());
        }
    }

    /**
     * Create a Stripe Subscription
     *
     * @param array $params
     * @return Subscription
     * @throws \Exception
     */
    protected function createStripeSubscription(array $params): Subscription
    {
        try {
            return Subscription::create($params);
        } catch (ApiErrorException $e) {
            throw new \Exception(SubscriptionPaymentIntentMessages::SUBSCRIPTION_CREATION_FAILED . ': ' . $e->getMessage());
        }
    }

    /**
     * Calculate discount amount
     *
     * @param int $amount
     * @param string $discountType
     * @param int $discountValue
     * @return int
     */
    protected function calculateDiscount(int $amount, string $discountType, int $discountValue): int
    {
        if ($discountType === DiscountType::PERCENTAGE) {
            return (int) round(($amount * $discountValue) / 100);
        }

        return min($discountValue, $amount);
    }

    /**
     * Map Stripe subscription status to application status
     *
     * @param string $stripeStatus
     * @return string
     */
    protected function mapSubscriptionStatus(string $stripeStatus): string
    {
        return match ($stripeStatus) {
            'active' => PaymentStatus::PAID,
            'trialing' => PaymentStatus::PENDING,
            'incomplete' => PaymentStatus::PENDING,
            'incomplete_expired' => PaymentStatus::FAILED,
            'past_due' => PaymentStatus::FAILED,
            'canceled' => PaymentStatus::CANCELLED,
            'unpaid' => PaymentStatus::FAILED,
            default => PaymentStatus::PENDING,
        };
    }

    /**
     * Confirm subscription payment
     *
     * @param string $subscriptionId
     * @return array
     * @throws \Exception
     */
    public function confirmSubscription(string $subscriptionId): array
    {
        try {
            $subscription = Subscription::retrieve($subscriptionId);

            $payment = $this->paymentRepository->findByPaymentIntentId($subscriptionId);

            $status = $this->mapSubscriptionStatus($subscription->status);

            if ($payment) {
                $updateData = ['status' => $status];
                if ($subscription->status === 'active') {
                    $updateData['paid_at'] = now();
                }
                $this->paymentRepository->update($payment, $updateData);
            }

            return [
                'status' => $status,
                'subscription' => [
                    'id' => $subscription->id,
                    'status' => $subscription->status,
                    'current_period_start' => $subscription->current_period_start,
                    'current_period_end' => $subscription->current_period_end,
                ],
                'payment' => $payment?->fresh(),
            ];
        } catch (ApiErrorException $e) {
            throw new \Exception(SubscriptionPaymentIntentMessages::PAYMENT_INTENT_RETRIEVAL_FAILED . ': ' . $e->getMessage());
        }
    }
}
