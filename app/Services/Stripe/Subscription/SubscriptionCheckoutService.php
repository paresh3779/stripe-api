<?php

declare(strict_types=1);

namespace App\Services\Stripe\Subscription;

use App\Constants\SubscriptionMessages;
use App\Models\User;
use App\Models\Subscription;
use App\Models\Invoice;
use App\Models\StripeCustomer;
use App\Repositories\Stripe\SubscriptionRepository;
use App\Repositories\Stripe\StripeCustomerRepository;
use App\Mail\SubscriptionCreatedMail;
use App\Mail\SubscriptionCancelledMail;
use App\Mail\SubscriptionRefundedMail;
use App\Mail\InvoicePaidMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\Checkout\Session;
use Stripe\Subscription as StripeSubscription;
use Stripe\Refund;
use Stripe\Invoice as StripeInvoice;
use Stripe\Exception\ApiErrorException;

/**
 * Comprehensive service for subscription checkout and management
 * Handles: products, checkout, subscriptions list, cancel, refund, invoices
 */
class SubscriptionCheckoutService
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

    // ==================== Products ====================

    /**
     * Get all subscription products
     */
    public function getProducts(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->subscriptionRepository->getSubscriptionProducts();
    }

    /**
     * Get a single subscription product with prices
     */
    public function getProductWithPrices(string $productId): object
    {
        $product = $this->subscriptionRepository->getSubscriptionProductById($productId);

        if (!$product) {
            throw new \Exception(SubscriptionMessages::PRODUCT_NOT_FOUND);
        }

        return $product;
    }

    // ==================== Customer Management ====================

    /**
     * Get or create a Stripe customer for the user
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
            Log::error('Failed to create Stripe customer', ['error' => $e->getMessage()]);
            throw new \Exception(SubscriptionMessages::CUSTOMER_CREATION_FAILED);
        }
    }

    // ==================== Checkout ====================

    /**
     * Create a subscription checkout session
     */
    public function createCheckoutSession(string $priceId, User $user): array
    {
        $price = $this->validateRecurringPrice($priceId);
        $stripeCustomerId = $this->getOrCreateStripeCustomer($user);

        $sessionConfig = [
            'payment_method_types' => ['card'],
            'customer' => $stripeCustomerId,
            'line_items' => [[
                'price' => $price->stripe_price_id,
                'quantity' => 1,
            ]],
            'mode' => 'subscription',
            'success_url' => config('app.frontend_url') . '/main/stripe-subscription-checkout/subscription/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => config('app.frontend_url') . '/main/stripe-subscription-checkout/subscription',
            'metadata' => [
                'user_id' => $user->id,
                'product_id' => $price->product_id,
                'price_id' => $price->id,
                'product_name' => $price->product->name,
                'billing_interval' => $price->interval,
            ],
            'subscription_data' => [
                'metadata' => [
                    'user_id' => $user->id,
                    'product_id' => $price->product_id,
                    'price_id' => $price->id,
                ],
            ],
        ];

        try {
            $session = Session::create($sessionConfig);

            Log::info('Subscription checkout session created', [
                'session_id' => $session->id,
                'user_id' => $user->id,
                'price_id' => $priceId,
            ]);

            return [
                'sessionId' => $session->id,
                'url' => $session->url,
            ];
        } catch (ApiErrorException $e) {
            Log::error('Failed to create checkout session', ['error' => $e->getMessage()]);
            throw new \Exception(SubscriptionMessages::CHECKOUT_SESSION_FAILED . ': ' . $e->getMessage());
        }
    }

    /**
     * Validate recurring price
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

    // ==================== Subscription Management ====================

    /**
     * Get user's subscriptions with related data
     */
    public function getUserSubscriptions(User $user): Collection
    {
        return Subscription::with(['product', 'price', 'invoices' => function ($query) {
            $query->orderBy('created_at', 'desc')->limit(5);
        }])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($subscription) {
                return [
                    'id' => $subscription->id,
                    'stripe_subscription_id' => $subscription->stripe_subscription_id,
                    'status' => $subscription->status,
                    'product' => [
                        'id' => $subscription->product->id,
                        'name' => $subscription->product->name,
                        'description' => $subscription->product->description,
                    ],
                    'price' => [
                        'id' => $subscription->price->id,
                        'amount' => $subscription->amount,
                        'currency' => $subscription->currency,
                        'interval' => $subscription->billing_interval,
                    ],
                    'current_period_start' => $subscription->current_period_start?->toISOString(),
                    'current_period_end' => $subscription->current_period_end?->toISOString(),
                    'trial_end' => $subscription->trial_end?->toISOString(),
                    'canceled_at' => $subscription->canceled_at?->toISOString(),
                    'cancel_at_period_end' => $subscription->cancel_at_period_end,
                    'can_cancel' => $subscription->isActive(),
                    'can_refund' => $subscription->canRefund(),
                    'days_until_refund_expires' => $subscription->canRefund() 
                        ? 7 - $subscription->created_at->diffInDays(now()) 
                        : 0,
                    'created_at' => $subscription->created_at->toISOString(),
                    'invoices' => $subscription->invoices->map(fn($inv) => [
                        'id' => $inv->id,
                        'number' => $inv->number,
                        'status' => $inv->status,
                        'amount' => $inv->total,
                        'currency' => $inv->currency,
                        'paid_at' => $inv->paid_at?->toISOString(),
                        'invoice_pdf' => $inv->invoice_pdf,
                        'hosted_invoice_url' => $inv->hosted_invoice_url,
                    ]),
                ];
            });
    }

    /**
     * Get a single subscription by ID
     */
    public function getSubscription(string $subscriptionId, User $user): ?Subscription
    {
        return Subscription::with(['product', 'price', 'invoices'])
            ->where('id', $subscriptionId)
            ->where('user_id', $user->id)
            ->first();
    }

    /**
     * Cancel subscription
     * If within 7 days, also processes refund
     */
    public function cancelSubscription(string $subscriptionId, User $user, bool $immediate = false): array
    {
        $subscription = $this->getSubscription($subscriptionId, $user);

        if (!$subscription) {
            throw new \Exception(SubscriptionMessages::SUBSCRIPTION_NOT_FOUND);
        }

        if (!$subscription->isActive()) {
            throw new \Exception('Subscription is not active');
        }

        try {
            $stripeSubscription = StripeSubscription::retrieve($subscription->stripe_subscription_id);

            // Check if eligible for refund (within 7 days)
            $canRefund = $subscription->canRefund();
            $refundResult = null;

            if ($canRefund && $immediate) {
                // Cancel immediately and process refund
                $stripeSubscription->cancel();
                $refundResult = $this->processRefund($subscription);

                $subscription->update([
                    'status' => 'canceled',
                    'canceled_at' => now(),
                    'ended_at' => now(),
                ]);

                // Send refund email
                $this->sendRefundEmail($subscription, $refundResult);

                Log::info('Subscription cancelled with refund', [
                    'subscription_id' => $subscription->id,
                    'refund_amount' => $refundResult['amount'] ?? 0,
                ]);
            } else {
                // Cancel at period end (no refund)
                $stripeSubscription->update(['cancel_at_period_end' => true]);

                $subscription->update([
                    'cancel_at_period_end' => true,
                    'canceled_at' => now(),
                ]);

                // Send cancellation email
                $this->sendCancellationEmail($subscription);

                Log::info('Subscription set to cancel at period end', [
                    'subscription_id' => $subscription->id,
                ]);
            }

            return [
                'success' => true,
                'message' => $canRefund && $immediate 
                    ? SubscriptionMessages::SUBSCRIPTION_CANCELLED . ' with refund processed.'
                    : SubscriptionMessages::SUBSCRIPTION_CANCELLED,
                'refund' => $refundResult,
                'subscription' => $subscription->fresh(),
            ];
        } catch (ApiErrorException $e) {
            Log::error('Failed to cancel subscription', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Failed to cancel subscription: ' . $e->getMessage());
        }
    }

    /**
     * Process refund for subscription
     */
    protected function processRefund(Subscription $subscription): array
    {
        try {
            // Get the latest paid invoice for this subscription
            $invoice = Invoice::where('subscription_id', $subscription->id)
                ->where('status', 'paid')
                ->orderBy('paid_at', 'desc')
                ->first();

            if (!$invoice) {
                return ['success' => false, 'message' => 'No paid invoice found for refund'];
            }

            // Retrieve the Stripe invoice to get the charge ID
            $stripeInvoice = StripeInvoice::retrieve($invoice->stripe_invoice_id);
            
            if (!$stripeInvoice->charge) {
                return ['success' => false, 'message' => 'No charge found for refund'];
            }

            // Create refund
            $refund = Refund::create([
                'charge' => $stripeInvoice->charge,
                'reason' => 'requested_by_customer',
            ]);

            Log::info('Refund processed', [
                'refund_id' => $refund->id,
                'subscription_id' => $subscription->id,
                'amount' => $refund->amount,
            ]);

            return [
                'success' => true,
                'refund_id' => $refund->id,
                'amount' => $refund->amount,
                'currency' => $refund->currency,
                'status' => $refund->status,
            ];
        } catch (ApiErrorException $e) {
            Log::error('Failed to process refund', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ==================== Invoice Management ====================

    /**
     * Get user's invoices
     */
    public function getUserInvoices(User $user, ?string $subscriptionId = null): Collection
    {
        $query = Invoice::with('subscription.product')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc');

        if ($subscriptionId) {
            $query->where('subscription_id', $subscriptionId);
        }

        return $query->get()->map(function ($invoice) {
            return [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'stripe_invoice_id' => $invoice->stripe_invoice_id,
                'status' => $invoice->status,
                'amount_due' => $invoice->amount_due,
                'amount_paid' => $invoice->amount_paid,
                'subtotal' => $invoice->subtotal,
                'total' => $invoice->total,
                'tax' => $invoice->tax,
                'currency' => $invoice->currency,
                'description' => $invoice->description,
                'hosted_invoice_url' => $invoice->hosted_invoice_url,
                'invoice_pdf' => $invoice->invoice_pdf,
                'due_date' => $invoice->due_date?->toISOString(),
                'paid_at' => $invoice->paid_at?->toISOString(),
                'period_start' => $invoice->period_start?->toISOString(),
                'period_end' => $invoice->period_end?->toISOString(),
                'subscription' => $invoice->subscription ? [
                    'id' => $invoice->subscription->id,
                    'product_name' => $invoice->subscription->product->name ?? 'Unknown',
                ] : null,
                'created_at' => $invoice->created_at->toISOString(),
            ];
        });
    }

    /**
     * Get single invoice
     */
    public function getInvoice(string $invoiceId, User $user): ?Invoice
    {
        return Invoice::with('subscription.product')
            ->where('id', $invoiceId)
            ->where('user_id', $user->id)
            ->first();
    }

    /**
     * Download invoice PDF (returns URL)
     */
    public function getInvoicePdfUrl(string $invoiceId, User $user): ?string
    {
        $invoice = $this->getInvoice($invoiceId, $user);
        return $invoice?->invoice_pdf;
    }

    // ==================== Email Notifications ====================

    /**
     * Send subscription created email
     */
    public function sendSubscriptionCreatedEmail(Subscription $subscription): void
    {
        try {
            $user = $subscription->user;
            if ($user && $user->email) {
                Mail::to($user->email)->queue(new SubscriptionCreatedMail($subscription));
                Log::info('Subscription created email queued', ['subscription_id' => $subscription->id]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send subscription created email', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Send cancellation email
     */
    protected function sendCancellationEmail(Subscription $subscription): void
    {
        try {
            $user = $subscription->user;
            if ($user && $user->email) {
                Mail::to($user->email)->queue(new SubscriptionCancelledMail($subscription));
                Log::info('Subscription cancelled email queued', ['subscription_id' => $subscription->id]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send cancellation email', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Send refund email
     */
    protected function sendRefundEmail(Subscription $subscription, array $refundResult): void
    {
        try {
            $user = $subscription->user;
            if ($user && $user->email) {
                Mail::to($user->email)->queue(new SubscriptionRefundedMail($subscription, $refundResult));
                Log::info('Subscription refunded email queued', ['subscription_id' => $subscription->id]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send refund email', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Send invoice paid email
     */
    public function sendInvoicePaidEmail(Invoice $invoice): void
    {
        try {
            $user = $invoice->user;
            if ($user && $user->email) {
                Mail::to($user->email)->queue(new InvoicePaidMail($invoice));
                Log::info('Invoice paid email queued', ['invoice_id' => $invoice->id]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send invoice paid email', ['error' => $e->getMessage()]);
        }
    }

    // ==================== Webhook Helpers ====================

    /**
     * Create or update subscription from Stripe webhook
     */
    public function syncSubscriptionFromStripe(object $stripeSubscription): ?Subscription
    {
        $stripeCustomer = StripeCustomer::where('stripe_customer_id', $stripeSubscription->customer)->first();
        
        if (!$stripeCustomer) {
            Log::warning('StripeCustomer not found for subscription sync', [
                'stripe_customer_id' => $stripeSubscription->customer,
            ]);
            return null;
        }

        $metadata = $stripeSubscription->metadata ?? (object)[];
        $item = $stripeSubscription->items->data[0] ?? null;

        $subscriptionData = [
            'user_id' => $stripeCustomer->user_id,
            'product_id' => $metadata->product_id ?? null,
            'price_id' => $metadata->price_id ?? null,
            'stripe_customer_id' => $stripeSubscription->customer,
            'status' => $stripeSubscription->status,
            'billing_interval' => $item?->price?->recurring?->interval ?? 'month',
            'interval_count' => $item?->price?->recurring?->interval_count ?? 1,
            'amount' => $item?->price?->unit_amount ?? 0,
            'currency' => $stripeSubscription->currency,
            'trial_start' => $stripeSubscription->trial_start 
                ? \Carbon\Carbon::createFromTimestamp($stripeSubscription->trial_start) 
                : null,
            'trial_end' => $stripeSubscription->trial_end 
                ? \Carbon\Carbon::createFromTimestamp($stripeSubscription->trial_end) 
                : null,
            'current_period_start' => \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_start),
            'current_period_end' => \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end),
            'canceled_at' => $stripeSubscription->canceled_at 
                ? \Carbon\Carbon::createFromTimestamp($stripeSubscription->canceled_at) 
                : null,
            'ended_at' => $stripeSubscription->ended_at 
                ? \Carbon\Carbon::createFromTimestamp($stripeSubscription->ended_at) 
                : null,
            'cancel_at_period_end' => $stripeSubscription->cancel_at_period_end,
            'metadata' => (array)$metadata,
        ];

        return Subscription::updateOrCreate(
            ['stripe_subscription_id' => $stripeSubscription->id],
            $subscriptionData
        );
    }

    /**
     * Create or update invoice from Stripe webhook
     */
    public function syncInvoiceFromStripe(object $stripeInvoice): ?Invoice
    {
        $stripeCustomer = StripeCustomer::where('stripe_customer_id', $stripeInvoice->customer)->first();
        
        if (!$stripeCustomer) {
            Log::warning('StripeCustomer not found for invoice sync', [
                'stripe_customer_id' => $stripeInvoice->customer,
            ]);
            return null;
        }

        // Find related subscription if any
        $subscription = null;
        if ($stripeInvoice->subscription) {
            $subscription = Subscription::where('stripe_subscription_id', $stripeInvoice->subscription)->first();
        }

        $lineItems = [];
        if ($stripeInvoice->lines && $stripeInvoice->lines->data) {
            foreach ($stripeInvoice->lines->data as $line) {
                $lineItems[] = [
                    'description' => $line->description,
                    'amount' => $line->amount,
                    'quantity' => $line->quantity ?? 1,
                ];
            }
        }

        $invoiceData = [
            'user_id' => $stripeCustomer->user_id,
            'subscription_id' => $subscription?->id,
            'stripe_customer_id' => $stripeInvoice->customer,
            'number' => $stripeInvoice->number,
            'status' => $stripeInvoice->status,
            'amount_due' => $stripeInvoice->amount_due,
            'amount_paid' => $stripeInvoice->amount_paid,
            'amount_remaining' => $stripeInvoice->amount_remaining,
            'subtotal' => $stripeInvoice->subtotal,
            'total' => $stripeInvoice->total,
            'tax' => $stripeInvoice->tax ?? 0,
            'currency' => $stripeInvoice->currency,
            'description' => $stripeInvoice->description,
            'hosted_invoice_url' => $stripeInvoice->hosted_invoice_url,
            'invoice_pdf' => $stripeInvoice->invoice_pdf,
            'due_date' => $stripeInvoice->due_date 
                ? \Carbon\Carbon::createFromTimestamp($stripeInvoice->due_date) 
                : null,
            'paid_at' => $stripeInvoice->status === 'paid' && $stripeInvoice->status_transitions?->paid_at
                ? \Carbon\Carbon::createFromTimestamp($stripeInvoice->status_transitions->paid_at)
                : null,
            'period_start' => $stripeInvoice->period_start 
                ? \Carbon\Carbon::createFromTimestamp($stripeInvoice->period_start) 
                : null,
            'period_end' => $stripeInvoice->period_end 
                ? \Carbon\Carbon::createFromTimestamp($stripeInvoice->period_end) 
                : null,
            'line_items' => $lineItems,
            'metadata' => (array)($stripeInvoice->metadata ?? []),
        ];

        return Invoice::updateOrCreate(
            ['stripe_invoice_id' => $stripeInvoice->id],
            $invoiceData
        );
    }
}
