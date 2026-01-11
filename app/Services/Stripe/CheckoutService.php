<?php

namespace App\Services\Stripe;

use App\Models\User;
use App\Repositories\Stripe\ProductRepository;
use App\Repositories\Stripe\PriceRepository;
use App\Repositories\Stripe\StripeCustomerRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\Checkout\Session;

class CheckoutService
{
    // Supported currencies for one-time checkout
    private const SUPPORTED_CURRENCIES = ['usd', 'eur', 'gbp', 'inr', 'aud', 'cad'];
    
    // Minimum and maximum amounts (in cents)
    private const MIN_AMOUNT = 50; // $0.50 minimum (Stripe requirement)
    private const MAX_AMOUNT = 99999999; // $999,999.99 maximum

    public function __construct(
        protected readonly ProductRepository $productRepository,
        protected readonly PriceRepository $priceRepository,
        protected readonly StripeCustomerRepository $customerRepository
    ) {
        Stripe::setApiKey(config('stripe.secret'));
    }

    public function getProducts(): Collection
    {
        return $this->productRepository->getOneTimeProducts();
    }

    public function getProductWithPrices(string $productId): object
    {
        $product = $this->productRepository->getProductWithPrices($productId);
        
        if (!$product) {
            throw new \Exception('Product not found');
        }

        return $product;
    }

    public function createCheckoutSession(string $priceId, User $user): array
    {
        $price = $this->priceRepository->getPriceById($priceId);

        if (!$price) {
            throw new \Exception('Price not found');
        }

        if ($price->type !== 'one_time') {
            throw new \Exception('This price is not for one-time payment');
        }

        // Validate amount
        $this->validateAmount($price->amount, $price->currency);

        // Validate currency
        $this->validateCurrency($price->currency);

        $stripeCustomerId = $this->getOrCreateStripeCustomer($user);

        // Generate idempotency key to prevent duplicate sessions
        $idempotencyKey = $this->generateIdempotencyKey($user->id, $priceId);

        $sessionData = [
            'payment_method_types' => ['card'],
            'customer' => $stripeCustomerId,
            'line_items' => [
                [
                    'price' => $price->stripe_price_id,
                    'quantity' => 1,
                ]
            ],
            'mode' => 'payment',
            'success_url' => config('app.frontend_url') . '/main/stripe-checkout/basic?status=success&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => config('app.frontend_url') . '/main/stripe-checkout/basic?status=cancelled',
            'metadata' => [
                'user_id'        => (string) $user->id,
                'product_id'     => (string) $price->product->id,
                'price_id'       => (string) $price->id,
                'idempotency_key' => $idempotencyKey,
            ],
            // Expire session after 30 minutes to prevent stale sessions
            'expires_at' => time() + (30 * 60),
        ];

        if ($user->id) {
            $sessionData['client_reference_id'] = (string) $user->id;
        }

        try {
            $session = Session::create($sessionData, [
                'idempotency_key' => $idempotencyKey,
            ]);

            \Log::info('CheckoutService: Session created', [
                'session_id' => $session->id,
                'user_id' => $user->id,
                'price_id' => $priceId,
                'amount' => $price->amount,
            ]);

            return [
                'sessionId' => $session->id,
                'url' => $session->url,
            ];
        } catch (\Stripe\Exception\CardException $e) {
            \Log::error('CheckoutService: Card error', [
                'error' => $e->getMessage(),
                'code' => $e->getStripeCode(),
            ]);
            throw new \Exception('Card error: ' . $e->getMessage());
        } catch (\Stripe\Exception\RateLimitException $e) {
            \Log::error('CheckoutService: Rate limit exceeded', [
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Too many requests. Please try again later.');
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            \Log::error('CheckoutService: Invalid request', [
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Invalid request: ' . $e->getMessage());
        } catch (\Stripe\Exception\AuthenticationException $e) {
            \Log::error('CheckoutService: Authentication failed', [
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Payment service configuration error.');
        } catch (\Stripe\Exception\ApiConnectionException $e) {
            \Log::error('CheckoutService: API connection failed', [
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Could not connect to payment service. Please try again.');
        } catch (\Stripe\Exception\ApiErrorException $e) {
            \Log::error('CheckoutService: Stripe API error', [
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Payment service error. Please try again.');
        }
    }

    /**
     * Validate payment amount
     */
    private function validateAmount(int $amount, string $currency): void
    {
        if ($amount < self::MIN_AMOUNT) {
            throw new \Exception("Amount must be at least " . (self::MIN_AMOUNT / 100) . " {$currency}");
        }

        if ($amount > self::MAX_AMOUNT) {
            throw new \Exception("Amount exceeds maximum allowed");
        }

        if ($amount <= 0) {
            throw new \Exception("Amount must be positive");
        }
    }

    /**
     * Validate currency
     */
    private function validateCurrency(string $currency): void
    {
        $currency = strtolower($currency);
        
        if (!in_array($currency, self::SUPPORTED_CURRENCIES)) {
            throw new \Exception("Currency '{$currency}' is not supported");
        }
    }

    /**
     * Generate idempotency key for checkout session
     * This prevents duplicate checkout sessions if user clicks multiple times
     */
    private function generateIdempotencyKey(string $userId, string $priceId): string
    {
        // Key is valid for 5 minutes - allows retry but prevents rapid duplicates
        $timeWindow = floor(time() / 300);
        return hash('sha256', "checkout:{$userId}:{$priceId}:{$timeWindow}");
    }

    /**
     * Verify checkout session status
     */
    public function verifyCheckoutSession(string $sessionId, User $user): array
    {
        try {
            $session = Session::retrieve($sessionId);

            // Verify the session belongs to this user
            if ($session->client_reference_id && $session->client_reference_id !== (string) $user->id) {
                throw new \Exception('Session does not belong to this user');
            }

            $paymentStatus = $session->payment_status;
            $status = $session->status;

            return [
                'verified' => true,
                'session_id' => $session->id,
                'status' => $status,
                'payment_status' => $paymentStatus,
                'customer_email' => $session->customer_details->email ?? null,
                'amount_total' => $session->amount_total,
                'currency' => $session->currency,
                'payment_intent' => $session->payment_intent,
                'is_paid' => $paymentStatus === 'paid',
                'is_complete' => $status === 'complete' && $paymentStatus === 'paid',
            ];
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            throw new \Exception('Invalid session ID');
        }
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

}
