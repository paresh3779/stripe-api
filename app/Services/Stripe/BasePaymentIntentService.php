<?php

namespace App\Services\Stripe;

use App\Repositories\Stripe\ProductRepository;
use App\Repositories\Stripe\PriceRepository;
use App\Repositories\Stripe\StripeCustomerRepository;
use App\Repositories\Stripe\PaymentRepository;
use App\Models\User;
use App\Models\Price;
use App\Models\Invoice;
use App\Constants\Messages;
use App\Constants\PaymentStatus;
use App\Constants\DiscountType;
use App\Mail\PaymentReceiptMail;
use App\Mail\PaymentFailedMail;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\PaymentIntent;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\CardException;
use Stripe\Exception\RateLimitException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Exception\AuthenticationException;
use Stripe\Exception\ApiConnectionException;

abstract class BasePaymentIntentService
{
    // Supported currencies
    protected const SUPPORTED_CURRENCIES = ['usd', 'eur', 'gbp', 'inr', 'aud', 'cad'];
    
    // Minimum and maximum amounts (in cents)
    protected const MIN_AMOUNT = 50; // $0.50 minimum (Stripe requirement)
    protected const MAX_AMOUNT = 99999999; // $999,999.99 maximum

    public function __construct(
        protected readonly ProductRepository $productRepository,
        protected readonly PriceRepository $priceRepository,
        protected readonly StripeCustomerRepository $stripeCustomerRepository,
        protected readonly PaymentRepository $paymentRepository
    ) {
        Stripe::setApiKey(config('stripe.secret'));
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

        // Validate amount
        $this->validateAmount($price->amount, $price->currency);

        // Validate currency
        $this->validateCurrency($price->currency);

        $product = $price->product;

        if (!$product || !$product->active || $product->is_archived) {
            throw new \Exception(Messages::PRODUCT_NOT_AVAILABLE);
        }

        return $price;
    }

    /**
     * Validate payment amount
     */
    protected function validateAmount(int $amount, string $currency): void
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
    protected function validateCurrency(string $currency): void
    {
        $currency = strtolower($currency);
        
        if (!in_array($currency, self::SUPPORTED_CURRENCIES)) {
            throw new \Exception("Currency '{$currency}' is not supported");
        }
    }

    /**
     * Generate idempotency key for payment intent
     */
    protected function generateIdempotencyKey(string $userId, string $priceId): string
    {
        $timeWindow = floor(time() / 300); // 5 minute window
        return hash('sha256', "payment_intent:{$userId}:{$priceId}:{$timeWindow}");
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
     * Create Stripe PaymentIntent with proper error handling
     *
     * @param array $params
     * @param string|null $idempotencyKey
     * @return PaymentIntent
     * @throws \Exception
     */
    protected function createStripePaymentIntent(array $params, ?string $idempotencyKey = null): PaymentIntent
    {
        try {
            $options = $idempotencyKey ? ['idempotency_key' => $idempotencyKey] : [];
            
            \Log::info('PaymentIntentService: Creating payment intent', [
                'amount' => $params['amount'] ?? null,
                'currency' => $params['currency'] ?? null,
                'customer' => $params['customer'] ?? null,
            ]);

            return PaymentIntent::create($params, $options);
        } catch (CardException $e) {
            \Log::error('PaymentIntentService: Card error', [
                'error' => $e->getMessage(),
                'code' => $e->getStripeCode(),
            ]);
            throw new \Exception('Card error: ' . $e->getMessage());
        } catch (RateLimitException $e) {
            \Log::error('PaymentIntentService: Rate limit exceeded', [
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Too many requests. Please try again later.');
        } catch (InvalidRequestException $e) {
            \Log::error('PaymentIntentService: Invalid request', [
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Invalid request: ' . $e->getMessage());
        } catch (AuthenticationException $e) {
            \Log::error('PaymentIntentService: Authentication failed', [
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Payment service configuration error.');
        } catch (ApiConnectionException $e) {
            \Log::error('PaymentIntentService: API connection failed', [
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Could not connect to payment service. Please try again.');
        } catch (ApiErrorException $e) {
            \Log::error('PaymentIntentService: Stripe API error', [
                'error' => $e->getMessage(),
            ]);
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
     * @param User|null $user
     * @return array
     * @throws \Exception
     */
    public function confirmPayment(string $paymentIntentId, ?User $user = null): array
    {
        try {
            $paymentIntent = PaymentIntent::retrieve($paymentIntentId, [
                'expand' => ['latest_charge', 'payment_method'],
            ]);

            $payment = $this->paymentRepository->findByPaymentIntentId($paymentIntentId);

            if (!$payment) {
                throw new \Exception(Messages::PAYMENT_RECORD_NOT_FOUND);
            }

            $status = $this->mapPaymentIntentStatus($paymentIntent->status);

            \Log::info('PaymentIntentService: Confirming payment', [
                'payment_intent_id' => $paymentIntentId,
                'stripe_status' => $paymentIntent->status,
                'mapped_status' => $status,
            ]);

            $updateData = [
                'status' => $status,
            ];

            if ($paymentIntent->status === 'succeeded') {
                $updateData['paid_at'] = now();
                
                // Get charge details
                $charge = $paymentIntent->latest_charge;
                if ($charge) {
                    $updateData['stripe_charge_id'] = $charge->id;
                    $updateData['payment_method'] = $charge->payment_method_details->type ?? 'card';
                }

                // Update payment within transaction
                DB::transaction(function () use ($payment, $updateData, $paymentIntent) {
                    $this->paymentRepository->update($payment, $updateData);
                    
                    // Create invoice for the payment
                    $this->createInvoiceForPayment($payment->fresh(), $paymentIntent);
                });

                // Handle post-payment actions (email, etc.)
                $this->handleSuccessfulPayment($paymentIntent, $payment->fresh());
            } elseif (in_array($paymentIntent->status, ['requires_payment_method', 'canceled'])) {
                $this->paymentRepository->update($payment, $updateData);
                
                // Handle failed payment
                $this->handleFailedPayment($paymentIntent, $payment->fresh());
            } else {
                $this->paymentRepository->update($payment, $updateData);
            }

            $freshPayment = $payment->fresh();

            return [
                'status' => $status,
                'payment' => $freshPayment,
                'invoice' => $freshPayment->invoice ?? null,
                'paymentIntent' => [
                    'id' => $paymentIntent->id,
                    'status' => $paymentIntent->status,
                    'amount' => $paymentIntent->amount,
                    'currency' => $paymentIntent->currency,
                ],
            ];
        } catch (ApiErrorException $e) {
            \Log::error('PaymentIntentService: Error confirming payment', [
                'payment_intent_id' => $paymentIntentId,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception(Messages::PAYMENT_INTENT_RETRIEVAL_FAILED . ': ' . $e->getMessage());
        }
    }

    /**
     * Create invoice for a successful payment
     */
    protected function createInvoiceForPayment($payment, PaymentIntent $paymentIntent): Invoice
    {
        $invoiceNumber = $this->generateInvoiceNumber();

        $invoice = Invoice::create([
            'user_id' => $payment->user_id,
            'stripe_invoice_id' => null, // Not a Stripe invoice
            'stripe_customer_id' => $paymentIntent->customer,
            'number' => $invoiceNumber,
            'status' => 'paid',
            'amount_due' => $payment->amount,
            'amount_paid' => $payment->amount,
            'amount_remaining' => 0,
            'subtotal' => $payment->amount,
            'total' => $payment->amount,
            'tax' => 0,
            'currency' => $payment->currency,
            'description' => $payment->description ?? 'One-time payment',
            'paid_at' => now(),
            'line_items' => [
                [
                    'description' => $payment->product?->name ?? $payment->description,
                    'quantity' => 1,
                    'unit_amount' => $payment->amount,
                    'amount' => $payment->amount,
                ],
            ],
            'metadata' => [
                'payment_id' => $payment->id,
                'payment_intent_id' => $paymentIntent->id,
                'product_id' => $payment->product_id,
                'price_id' => $payment->price_id,
            ],
        ]);

        // Link invoice to payment
        $payment->update(['stripe_invoice_id' => $invoice->id]);

        \Log::info('PaymentIntentService: Invoice created', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoiceNumber,
            'payment_id' => $payment->id,
        ]);

        return $invoice;
    }

    /**
     * Generate unique invoice number
     */
    protected function generateInvoiceNumber(): string
    {
        $prefix = 'INV';
        $date = now()->format('Ymd');
        $random = strtoupper(Str::random(6));
        return "{$prefix}-{$date}-{$random}";
    }

    /**
     * Handle successful payment - send receipt email
     */
    protected function handleSuccessfulPayment(PaymentIntent $paymentIntent, $payment): void
    {
        try {
            $user = $payment->user;
            
            if ($user && $user->email) {
                Mail::to($user->email)->queue(new PaymentReceiptMail($payment));
                
                \Log::info('PaymentIntentService: Payment receipt email queued', [
                    'payment_id' => $payment->id,
                    'user_email' => $user->email,
                ]);
            }
        } catch (\Exception $e) {
            // Log error but don't fail the payment confirmation
            \Log::error('PaymentIntentService: Failed to send receipt email', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle failed payment - send failure notification
     */
    protected function handleFailedPayment(PaymentIntent $paymentIntent, $payment): void
    {
        try {
            $user = $payment->user;
            
            if ($user && $user->email) {
                $failureMessage = $this->getFailureMessage($paymentIntent);
                
                Mail::to($user->email)->queue(new PaymentFailedMail(
                    $payment,
                    null,
                    $failureMessage
                ));
                
                \Log::info('PaymentIntentService: Payment failed email queued', [
                    'payment_id' => $payment->id,
                    'user_email' => $user->email,
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('PaymentIntentService: Failed to send failure email', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get user-friendly failure message
     */
    protected function getFailureMessage(PaymentIntent $paymentIntent): string
    {
        $charge = $paymentIntent->latest_charge;
        
        if ($charge && $charge->failure_message) {
            return $charge->failure_message;
        }

        $failureCode = $charge->failure_code ?? $paymentIntent->last_payment_error->code ?? null;

        return match ($failureCode) {
            'card_declined' => 'Your card was declined. Please try a different payment method.',
            'insufficient_funds' => 'Your card has insufficient funds.',
            'expired_card' => 'Your card has expired. Please use a different card.',
            'incorrect_cvc' => 'The CVC code is incorrect. Please check and try again.',
            'processing_error' => 'An error occurred while processing your card. Please try again.',
            'incorrect_number' => 'The card number is incorrect. Please check and try again.',
            default => 'Your payment could not be processed. Please try again or use a different payment method.',
        };
    }

    /**
     * Get payment status details
     */
    public function getPaymentStatus(string $paymentIntentId): array
    {
        try {
            $paymentIntent = PaymentIntent::retrieve($paymentIntentId);
            $payment = $this->paymentRepository->findByPaymentIntentId($paymentIntentId);

            return [
                'paymentIntentId' => $paymentIntent->id,
                'status' => $paymentIntent->status,
                'mappedStatus' => $this->mapPaymentIntentStatus($paymentIntent->status),
                'amount' => $paymentIntent->amount,
                'currency' => $paymentIntent->currency,
                'payment' => $payment,
                'requiresAction' => $paymentIntent->status === 'requires_action',
                'nextAction' => $paymentIntent->next_action,
            ];
        } catch (ApiErrorException $e) {
            throw new \Exception('Failed to retrieve payment status: ' . $e->getMessage());
        }
    }
}
