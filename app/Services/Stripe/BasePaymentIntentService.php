<?php

namespace App\Services\Stripe;

use App\Repositories\Stripe\ProductRepository;
use App\Repositories\Stripe\PriceRepository;
use App\Repositories\Stripe\StripeCustomerRepository;
use App\Repositories\Stripe\PaymentRepository;
use App\Models\User;
use App\Models\Price;
use App\Constants\Messages;
use App\Constants\PaymentStatus;
use App\Constants\DiscountType;
use Illuminate\Support\Collection;
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\PaymentIntent;
use Stripe\Exception\ApiErrorException;

abstract class BasePaymentIntentService
{
    public function __construct(
        protected readonly ProductRepository $productRepository,
        protected readonly PriceRepository $priceRepository,
        protected readonly StripeCustomerRepository $stripeCustomerRepository,
        protected readonly PaymentRepository $paymentRepository
    ) {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Get all one-time products
     *
     * @return Collection
     */
    public function getProducts(): Collection
    {
        return $this->productRepository->getOneTimeProducts();
    }

    /**
     * Get product with prices by ID
     *
     * @param string $productId
     * @return object
     * @throws \Exception
     */
    public function getProductWithPrices(string $productId): object
    {
        $product = $this->productRepository->getProductWithPrices($productId);
        
        if (!$product) {
            throw new \Exception(Messages::PRODUCT_NOT_FOUND);
        }

        return $product;
    }

    /**
     * Validate price for one-time payment
     *
     * @param string $priceId
     * @return Price
     * @throws \Exception
     */
    protected function validatePrice(string $priceId): Price
    {
        $price = $this->priceRepository->getPriceById($priceId);

        if (!$price) {
            throw new \Exception(Messages::PRICE_NOT_FOUND);
        }

        if ($price->type !== 'one_time') {
            throw new \Exception(Messages::PRICE_NOT_ONE_TIME);
        }

        if (!$price->active) {
            throw new \Exception(Messages::PRICE_NOT_ACTIVE);
        }

        $product = $price->product;

        if (!$product || !$product->active || $product->is_archived) {
            throw new \Exception(Messages::PRODUCT_NOT_AVAILABLE);
        }

        return $price;
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
     * Get or create Stripe customer for user
     *
     * @param User $user
     * @return string Stripe customer ID
     * @throws ApiErrorException
     */
    protected function getOrCreateStripeCustomer(User $user): string
    {
        $stripeCustomer = $this->stripeCustomerRepository->findByUserId($user->id);

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

            $this->stripeCustomerRepository->create(
                $user->id,
                $customer->id,
                $user->email
            );

            return $customer->id;
        } catch (ApiErrorException $e) {
            throw new \Exception(Messages::STRIPE_CUSTOMER_CREATION_FAILED . ': ' . $e->getMessage());
        }
    }

    /**
     * Create Stripe PaymentIntent
     *
     * @param array $params
     * @return PaymentIntent
     * @throws \Exception
     */
    protected function createStripePaymentIntent(array $params): PaymentIntent
    {
        try {
            return PaymentIntent::create($params);
        } catch (ApiErrorException $e) {
            throw new \Exception(Messages::PAYMENT_INTENT_CREATION_FAILED . ': ' . $e->getMessage());
        }
    }

    /**
     * Map Stripe payment intent status to application status
     *
     * @param string $stripeStatus
     * @return string
     */
    protected function mapPaymentIntentStatus(string $stripeStatus): string
    {
        return match ($stripeStatus) {
            'succeeded' => PaymentStatus::SUCCEEDED,
            'processing' => PaymentStatus::PENDING,
            'requires_payment_method' => PaymentStatus::FAILED,
            'requires_confirmation' => PaymentStatus::PENDING,
            'requires_action' => PaymentStatus::PENDING,
            'canceled' => PaymentStatus::FAILED,
            default => PaymentStatus::PENDING,
        };
    }

    /**
     * Confirm payment status
     *
     * @param string $paymentIntentId
     * @return array
     * @throws \Exception
     */
    public function confirmPayment(string $paymentIntentId): array
    {
        try {
            $paymentIntent = PaymentIntent::retrieve($paymentIntentId);

            $payment = $this->paymentRepository->findByPaymentIntentId($paymentIntentId);

            if (!$payment) {
                throw new \Exception(Messages::PAYMENT_RECORD_NOT_FOUND);
            }

            $status = $this->mapPaymentIntentStatus($paymentIntent->status);

            $updateData = [
                'status' => $status,
            ];

            if ($paymentIntent->status === 'succeeded') {
                $updateData['paid_at'] = now();
                if ($paymentIntent->charges->data[0] ?? null) {
                    $updateData['stripe_charge_id'] = $paymentIntent->charges->data[0]->id;
                    $updateData['payment_method'] = $paymentIntent->charges->data[0]->payment_method_details->type ?? null;
                }

                $this->handleSuccessfulPayment($paymentIntent);
            }

            $this->paymentRepository->update($payment, $updateData);

            return [
                'status' => $status,
                'payment' => $payment->fresh(),
                'paymentIntent' => [
                    'id' => $paymentIntent->id,
                    'status' => $paymentIntent->status,
                    'amount' => $paymentIntent->amount,
                    'currency' => $paymentIntent->currency,
                ],
            ];
        } catch (ApiErrorException $e) {
            throw new \Exception(Messages::PAYMENT_INTENT_RETRIEVAL_FAILED . ': ' . $e->getMessage());
        }
    }

    /**
     * Handle successful payment (to be implemented by child classes if needed)
     *
     * @param PaymentIntent $paymentIntent
     * @return void
     */
    protected function handleSuccessfulPayment(PaymentIntent $paymentIntent): void
    {
        // Override in child classes if needed
    }
}
