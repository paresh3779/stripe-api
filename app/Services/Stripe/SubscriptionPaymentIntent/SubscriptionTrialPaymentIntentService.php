<?php

declare(strict_types=1);

namespace App\Services\Stripe\SubscriptionPaymentIntent;

use App\Constants\SubscriptionPaymentIntentMessages;
use App\Constants\PaymentStatus;
use App\Models\User;
use App\Models\Subscription;
use App\Models\Invoice;
use App\Models\StripeCustomer;
use App\Models\StripePaymentMethod;
use App\Repositories\Stripe\SubscriptionRepository;
use App\Repositories\Stripe\StripeCustomerRepository;
use App\Repositories\Stripe\PaymentRepository;
use App\Mail\SubscriptionCreatedMail;
use App\Mail\SubscriptionCancelledMail;
use App\Mail\SubscriptionRefundedMail;
use App\Mail\InvoicePaidMail;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\PaymentMethod;
use Stripe\Subscription as StripeSubscription;
use Stripe\Invoice as StripeInvoice;
use Stripe\Refund;
use Stripe\Exception\ApiErrorException;
use Carbon\Carbon;

/**
 * Comprehensive service for subscription PaymentIntent with 15-day trial period
 * Handles: Products, Customer, Checkout, Subscription Management, Invoices, Payment Methods, Email Notifications
 */
class SubscriptionTrialPaymentIntentService extends BaseSubscriptionPaymentIntentService
{
    private const TRIAL_DAYS = 15;
    private const REFUND_ELIGIBLE_DAYS = 7;

    public function __construct(
        SubscriptionRepository $subscriptionRepository,
        StripeCustomerRepository $customerRepository,
        PaymentRepository $paymentRepository
    ) {
        parent::__construct($subscriptionRepository, $customerRepository, $paymentRepository);
    }

    // ==================== Products ====================

    /**
     * Get subscription products that have trial periods available
     */
    public function getProductsWithTrial(): Collection
    {
        return $this->subscriptionRepository->getSubscriptionProductsWithTrial();
    }

    /**
     * Get trial information for a specific price
     */
    public function getTrialInfo(string $priceId): array
    {
        $price = $this->validateRecurringPrice($priceId);

        return [
            'has_trial' => true,
            'trial_days' => self::TRIAL_DAYS,
            'price' => $price,
        ];
    }

    // ==================== Customer & Payment Methods ====================

    /**
     * Get or create Stripe customer with enhanced logging
     */
    public function getOrCreateCustomer(User $user): array
    {
        $stripeCustomerId = $this->getOrCreateStripeCustomer($user);
        $stripeCustomer = StripeCustomer::where('stripe_customer_id', $stripeCustomerId)->first();

        return [
            'stripe_customer_id' => $stripeCustomerId,
            'customer_record' => $stripeCustomer,
        ];
    }

    /**
     * Get user's saved payment methods
     */
    public function getUserPaymentMethods(User $user): array
    {
        $stripeCustomer = StripeCustomer::where('user_id', $user->id)->first();

        if (!$stripeCustomer) {
            return [];
        }

        $paymentMethods = StripePaymentMethod::where('customer_id', $stripeCustomer->id)
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return $paymentMethods->map(function ($pm) {
            return [
                'id' => $pm->id,
                'stripe_payment_method_id' => $pm->stripe_payment_method_id,
                'type' => $pm->type,
                'card_brand' => $pm->card_brand,
                'last4' => $pm->last4,
                'exp_month' => $pm->exp_month,
                'exp_year' => $pm->exp_year,
                'is_default' => $pm->is_default,
            ];
        })->toArray();
    }

    /**
     * Save payment method for future use
     */
    public function savePaymentMethod(string $paymentMethodId, User $user, bool $setDefault = true): array
    {
        try {
            $stripeCustomerId = $this->getOrCreateStripeCustomer($user);
            $stripeCustomer = StripeCustomer::where('stripe_customer_id', $stripeCustomerId)->first();

            $paymentMethod = $this->attachPaymentMethodToCustomer($paymentMethodId, $stripeCustomerId);

            // Reset default if setting new default
            if ($setDefault) {
                StripePaymentMethod::where('customer_id', $stripeCustomer->id)
                    ->update(['is_default' => false]);
            }

            $savedMethod = StripePaymentMethod::updateOrCreate(
                ['stripe_payment_method_id' => $paymentMethodId],
                [
                    'customer_id' => $stripeCustomer->id,
                    'type' => $paymentMethod->type,
                    'card_brand' => $paymentMethod->card->brand ?? null,
                    'last4' => $paymentMethod->card->last4 ?? null,
                    'exp_month' => $paymentMethod->card->exp_month ?? null,
                    'exp_year' => $paymentMethod->card->exp_year ?? null,
                    'is_default' => $setDefault,
                ]
            );

            Log::info('PaymentMethod saved', [
                'user_id' => $user->id,
                'payment_method_id' => $paymentMethodId,
            ]);

            return [
                'success' => true,
                'payment_method' => $savedMethod,
            ];
        } catch (ApiErrorException $e) {
            Log::error('Failed to save payment method', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);
            throw new \Exception('Failed to save payment method: ' . $e->getMessage());
        }
    }

    /**
     * Delete a saved payment method
     */
    public function deletePaymentMethod(string $paymentMethodId, User $user): bool
    {
        try {
            $stripeCustomer = StripeCustomer::where('user_id', $user->id)->first();
            if (!$stripeCustomer) {
                throw new \Exception('Customer not found');
            }

            $paymentMethod = StripePaymentMethod::where('customer_id', $stripeCustomer->id)
                ->where('stripe_payment_method_id', $paymentMethodId)
                ->first();

            if (!$paymentMethod) {
                throw new \Exception('Payment method not found');
            }

            // Detach from Stripe
            $stripePaymentMethod = PaymentMethod::retrieve($paymentMethodId);
            $stripePaymentMethod->detach();

            // Delete from database
            $paymentMethod->delete();

            Log::info('PaymentMethod deleted', [
                'user_id' => $user->id,
                'payment_method_id' => $paymentMethodId,
            ]);

            return true;
        } catch (ApiErrorException $e) {
            Log::error('Failed to delete payment method', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);
            throw new \Exception('Failed to delete payment method: ' . $e->getMessage());
        }
    }

    // ==================== Subscription Creation ====================

    /**
     * Create a SetupIntent for trial subscription (no immediate payment)
     */
    public function createSetupIntentForTrial(User $user, string $priceId): array
    {
        $price = $this->validateRecurringPrice($priceId);
        $stripeCustomerId = $this->getOrCreateStripeCustomer($user);

        $setupIntent = $this->createSetupIntent($stripeCustomerId, [
            'user_id' => $user->id,
            'price_id' => $price->id,
            'product_id' => $price->product_id,
            'has_trial' => true,
            'trial_days' => self::TRIAL_DAYS,
        ]);

        // Get existing payment methods
        $paymentMethods = $this->getUserPaymentMethods($user);

        return [
            'clientSecret' => $setupIntent->client_secret,
            'setupIntentId' => $setupIntent->id,
            'customerId' => $stripeCustomerId,
            'trialDays' => self::TRIAL_DAYS,
            'price' => [
                'id' => $price->id,
                'stripe_price_id' => $price->stripe_price_id,
                'amount' => $price->amount,
                'currency' => $price->currency,
                'interval' => $price->interval,
            ],
            'savedPaymentMethods' => $paymentMethods,
        ];
    }

    /**
     * Create a subscription with 15-day trial period
     */
    public function createSubscriptionWithTrial(string $priceId, string $paymentMethodId, User $user): array
    {
        $price = $this->validateRecurringPrice($priceId);
        $product = $price->product;

        $stripeCustomerId = $this->getOrCreateStripeCustomer($user);

        // Save the payment method
        $this->savePaymentMethod($paymentMethodId, $user, true);

        $stripeSubscription = $this->createStripeSubscription([
            'customer' => $stripeCustomerId,
            'items' => [
                ['price' => $price->stripe_price_id],
            ],
            'default_payment_method' => $paymentMethodId,
            'trial_period_days' => self::TRIAL_DAYS,
            'payment_settings' => [
                'save_default_payment_method' => 'on_subscription',
            ],
            'metadata' => [
                'user_id' => $user->id,
                'product_id' => $product->id,
                'price_id' => $price->id,
                'product_name' => $product->name,
                'billing_interval' => $price->interval,
                'has_trial' => 'true',
                'trial_days' => self::TRIAL_DAYS,
            ],
        ]);

        // Store subscription in database
        $subscription = $this->syncSubscriptionFromStripe($stripeSubscription);

        // Create payment record
        $payment = $this->paymentRepository->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'price_id' => $price->id,
            'stripe_payment_intent_id' => $stripeSubscription->id,
            'stripe_subscription_id' => $stripeSubscription->id,
            'amount' => 0,
            'currency' => $price->currency,
            'status' => PaymentStatus::PENDING,
            'billing_reason' => 'subscription_trial',
            'description' => $product->name . ' - ' . self::TRIAL_DAYS . ' Day Trial',
        ]);

        // Send subscription created email
        $this->sendSubscriptionCreatedEmail($subscription);

        Log::info('Trial subscription created', [
            'subscription_id' => $subscription->id,
            'stripe_subscription_id' => $stripeSubscription->id,
            'user_id' => $user->id,
        ]);

        return [
            'subscriptionId' => $stripeSubscription->id,
            'localSubscriptionId' => $subscription->id,
            'status' => $stripeSubscription->status,
            'trialEnd' => $stripeSubscription->trial_end,
            'trialDays' => self::TRIAL_DAYS,
            'amount' => $price->amount,
            'currency' => $price->currency,
            'interval' => $price->interval,
            'paymentId' => $payment->id,
            'subscription' => $this->formatSubscription($subscription),
        ];
    }

    /**
     * Create subscription using existing payment method
     */
    public function createSubscriptionWithExistingPaymentMethod(
        string $priceId,
        string $savedPaymentMethodId,
        User $user
    ): array {
        $price = $this->validateRecurringPrice($priceId);
        $product = $price->product;

        $stripeCustomer = StripeCustomer::where('user_id', $user->id)->first();
        if (!$stripeCustomer) {
            throw new \Exception('Customer not found. Please add a payment method first.');
        }

        $paymentMethod = StripePaymentMethod::where('customer_id', $stripeCustomer->id)
            ->where('stripe_payment_method_id', $savedPaymentMethodId)
            ->first();

        if (!$paymentMethod) {
            throw new \Exception('Payment method not found');
        }

        $stripeSubscription = $this->createStripeSubscription([
            'customer' => $stripeCustomer->stripe_customer_id,
            'items' => [
                ['price' => $price->stripe_price_id],
            ],
            'default_payment_method' => $savedPaymentMethodId,
            'trial_period_days' => self::TRIAL_DAYS,
            'payment_settings' => [
                'save_default_payment_method' => 'on_subscription',
            ],
            'metadata' => [
                'user_id' => $user->id,
                'product_id' => $product->id,
                'price_id' => $price->id,
                'product_name' => $product->name,
                'billing_interval' => $price->interval,
                'has_trial' => 'true',
                'trial_days' => self::TRIAL_DAYS,
            ],
        ]);

        $subscription = $this->syncSubscriptionFromStripe($stripeSubscription);

        $payment = $this->paymentRepository->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'price_id' => $price->id,
            'stripe_payment_intent_id' => $stripeSubscription->id,
            'stripe_subscription_id' => $stripeSubscription->id,
            'amount' => 0,
            'currency' => $price->currency,
            'status' => PaymentStatus::PENDING,
            'billing_reason' => 'subscription_trial',
            'description' => $product->name . ' - ' . self::TRIAL_DAYS . ' Day Trial',
        ]);

        $this->sendSubscriptionCreatedEmail($subscription);

        return [
            'subscriptionId' => $stripeSubscription->id,
            'localSubscriptionId' => $subscription->id,
            'status' => $stripeSubscription->status,
            'trialEnd' => $stripeSubscription->trial_end,
            'trialDays' => self::TRIAL_DAYS,
            'amount' => $price->amount,
            'currency' => $price->currency,
            'interval' => $price->interval,
            'paymentId' => $payment->id,
            'subscription' => $this->formatSubscription($subscription),
        ];
    }

    // ==================== Subscription Management ====================

    /**
     * Get user's subscriptions
     */
    public function getUserSubscriptions(User $user): array
    {
        $subscriptions = Subscription::where('user_id', $user->id)
            ->with(['product', 'price', 'invoices' => function ($query) {
                $query->orderBy('created_at', 'desc')->limit(5);
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        return $subscriptions->map(fn($sub) => $this->formatSubscription($sub))->toArray();
    }

    /**
     * Get a single subscription
     */
    public function getSubscription(string $subscriptionId, User $user): ?array
    {
        $subscription = Subscription::where('id', $subscriptionId)
            ->where('user_id', $user->id)
            ->with(['product', 'price', 'invoices'])
            ->first();

        if (!$subscription) {
            return null;
        }

        return $this->formatSubscription($subscription);
    }

    /**
     * Cancel subscription with optional immediate cancellation and refund
     */
    public function cancelSubscription(string $subscriptionId, User $user, bool $immediate = false): array
    {
        $subscription = Subscription::where('id', $subscriptionId)
            ->where('user_id', $user->id)
            ->first();

        if (!$subscription) {
            throw new \Exception('Subscription not found');
        }

        if (!$subscription->isActive()) {
            throw new \Exception('Subscription is not active');
        }

        try {
            $stripeSubscription = StripeSubscription::retrieve($subscription->stripe_subscription_id);
            $refundResult = null;

            if ($immediate && $subscription->canRefund()) {
                // Cancel immediately and process refund
                $stripeSubscription->cancel();

                // Process refund if there's a paid invoice
                $refundResult = $this->processRefund($subscription);

                $subscription->update([
                    'status' => 'canceled',
                    'canceled_at' => now(),
                    'ended_at' => now(),
                    'cancel_at_period_end' => false,
                ]);

                // Send refund email
                if ($refundResult && $refundResult['success']) {
                    $this->sendSubscriptionRefundedEmail($subscription, $refundResult);
                } else {
                    $this->sendSubscriptionCancelledEmail($subscription);
                }

                $message = 'Subscription cancelled immediately' . 
                    ($refundResult && $refundResult['success'] ? ' with refund processed' : '');
            } else {
                // Cancel at period end
                $stripeSubscription->update([
                    'cancel_at_period_end' => true,
                ]);

                $subscription->update([
                    'cancel_at_period_end' => true,
                ]);

                $this->sendSubscriptionCancelledEmail($subscription);
                $message = 'Subscription will be cancelled at the end of the billing period';
            }

            Log::info('Subscription cancelled', [
                'subscription_id' => $subscription->id,
                'immediate' => $immediate,
                'refund' => $refundResult,
            ]);

            return [
                'success' => true,
                'message' => $message,
                'subscription' => $this->formatSubscription($subscription->fresh()),
                'refund' => $refundResult,
            ];
        } catch (ApiErrorException $e) {
            Log::error('Failed to cancel subscription', [
                'error' => $e->getMessage(),
                'subscription_id' => $subscription->id,
            ]);
            throw new \Exception('Failed to cancel subscription: ' . $e->getMessage());
        }
    }

    /**
     * Process refund for subscription
     */
    private function processRefund(Subscription $subscription): ?array
    {
        try {
            // Get the latest paid invoice
            $invoice = Invoice::where('subscription_id', $subscription->id)
                ->where('status', 'paid')
                ->orderBy('paid_at', 'desc')
                ->first();

            if (!$invoice || !$invoice->stripe_payment_intent_id) {
                return ['success' => false, 'message' => 'No refundable payment found'];
            }

            $refund = Refund::create([
                'payment_intent' => $invoice->stripe_payment_intent_id,
                'reason' => 'requested_by_customer',
                'metadata' => [
                    'subscription_id' => $subscription->id,
                    'user_id' => $subscription->user_id,
                ],
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
                'error' => $e->getMessage(),
                'subscription_id' => $subscription->id,
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ==================== Invoice Management ====================

    /**
     * Get user's invoices
     */
    public function getUserInvoices(User $user, ?string $subscriptionId = null): array
    {
        $query = Invoice::where('user_id', $user->id)
            ->with(['subscription.product'])
            ->orderBy('created_at', 'desc');

        if ($subscriptionId) {
            $query->where('subscription_id', $subscriptionId);
        }

        $invoices = $query->get();

        return $invoices->map(fn($inv) => $this->formatInvoice($inv))->toArray();
    }

    /**
     * Get a single invoice
     */
    public function getInvoice(string $invoiceId, User $user): ?array
    {
        $invoice = Invoice::where('id', $invoiceId)
            ->where('user_id', $user->id)
            ->with(['subscription.product'])
            ->first();

        if (!$invoice) {
            return null;
        }

        return $this->formatInvoice($invoice);
    }

    /**
     * Get invoice PDF URL from Stripe
     */
    public function getInvoicePdfUrl(string $invoiceId, User $user): ?string
    {
        $invoice = Invoice::where('id', $invoiceId)
            ->where('user_id', $user->id)
            ->first();

        if (!$invoice) {
            return null;
        }

        if ($invoice->invoice_pdf) {
            return $invoice->invoice_pdf;
        }

        try {
            $stripeInvoice = StripeInvoice::retrieve($invoice->stripe_invoice_id);
            
            if ($stripeInvoice->invoice_pdf) {
                $invoice->update(['invoice_pdf' => $stripeInvoice->invoice_pdf]);
                return $stripeInvoice->invoice_pdf;
            }

            return null;
        } catch (ApiErrorException $e) {
            Log::error('Failed to get invoice PDF', [
                'error' => $e->getMessage(),
                'invoice_id' => $invoiceId,
            ]);
            return null;
        }
    }

    // ==================== Webhook Sync Helpers ====================

    /**
     * Sync subscription from Stripe webhook data
     */
    public function syncSubscriptionFromStripe($stripeSubscription): ?Subscription
    {
        try {
            $stripeCustomer = StripeCustomer::where('stripe_customer_id', $stripeSubscription->customer)->first();

            if (!$stripeCustomer) {
                Log::warning('syncSubscriptionFromStripe: StripeCustomer not found', [
                    'stripe_customer_id' => $stripeSubscription->customer,
                ]);
                return null;
            }

            $metadata = $stripeSubscription->metadata ?? new \stdClass();
            $priceData = $stripeSubscription->items->data[0]->price ?? null;

            $subscription = Subscription::updateOrCreate(
                ['stripe_subscription_id' => $stripeSubscription->id],
                [
                    'user_id' => $stripeCustomer->user_id,
                    'product_id' => $metadata->product_id ?? null,
                    'price_id' => $metadata->price_id ?? null,
                    'stripe_customer_id' => $stripeSubscription->customer,
                    'status' => $stripeSubscription->status,
                    'billing_interval' => $priceData?->recurring?->interval ?? $metadata->billing_interval ?? null,
                    'interval_count' => $priceData?->recurring?->interval_count ?? 1,
                    'amount' => $priceData?->unit_amount ?? 0,
                    'currency' => $stripeSubscription->currency ?? 'usd',
                    'trial_start' => $stripeSubscription->trial_start 
                        ? Carbon::createFromTimestamp($stripeSubscription->trial_start) 
                        : null,
                    'trial_end' => $stripeSubscription->trial_end 
                        ? Carbon::createFromTimestamp($stripeSubscription->trial_end) 
                        : null,
                    'current_period_start' => Carbon::createFromTimestamp($stripeSubscription->current_period_start),
                    'current_period_end' => Carbon::createFromTimestamp($stripeSubscription->current_period_end),
                    'canceled_at' => $stripeSubscription->canceled_at 
                        ? Carbon::createFromTimestamp($stripeSubscription->canceled_at) 
                        : null,
                    'ended_at' => $stripeSubscription->ended_at 
                        ? Carbon::createFromTimestamp($stripeSubscription->ended_at) 
                        : null,
                    'cancel_at_period_end' => $stripeSubscription->cancel_at_period_end ?? false,
                    'metadata' => (array) $metadata,
                ]
            );

            return $subscription;
        } catch (\Exception $e) {
            Log::error('syncSubscriptionFromStripe: Error', [
                'error' => $e->getMessage(),
                'stripe_subscription_id' => $stripeSubscription->id ?? 'unknown',
            ]);
            return null;
        }
    }

    /**
     * Sync invoice from Stripe webhook data
     */
    public function syncInvoiceFromStripe($stripeInvoice): ?Invoice
    {
        try {
            $stripeCustomer = StripeCustomer::where('stripe_customer_id', $stripeInvoice->customer)->first();

            if (!$stripeCustomer) {
                Log::warning('syncInvoiceFromStripe: StripeCustomer not found', [
                    'stripe_customer_id' => $stripeInvoice->customer,
                ]);
                return null;
            }

            $subscription = null;
            if ($stripeInvoice->subscription) {
                $subscription = Subscription::where('stripe_subscription_id', $stripeInvoice->subscription)->first();
            }

            $invoice = Invoice::updateOrCreate(
                ['stripe_invoice_id' => $stripeInvoice->id],
                [
                    'user_id' => $stripeCustomer->user_id,
                    'subscription_id' => $subscription?->id,
                    'number' => $stripeInvoice->number,
                    'stripe_payment_intent_id' => $stripeInvoice->payment_intent,
                    'status' => $stripeInvoice->status,
                    'amount_due' => $stripeInvoice->amount_due ?? 0,
                    'amount_paid' => $stripeInvoice->amount_paid ?? 0,
                    'subtotal' => $stripeInvoice->subtotal ?? 0,
                    'total' => $stripeInvoice->total ?? 0,
                    'tax' => $stripeInvoice->tax ?? 0,
                    'currency' => $stripeInvoice->currency ?? 'usd',
                    'description' => $stripeInvoice->description,
                    'hosted_invoice_url' => $stripeInvoice->hosted_invoice_url,
                    'invoice_pdf' => $stripeInvoice->invoice_pdf,
                    'due_date' => $stripeInvoice->due_date 
                        ? Carbon::createFromTimestamp($stripeInvoice->due_date) 
                        : null,
                    'paid_at' => $stripeInvoice->status === 'paid' && $stripeInvoice->status_transitions?->paid_at
                        ? Carbon::createFromTimestamp($stripeInvoice->status_transitions->paid_at)
                        : ($stripeInvoice->status === 'paid' ? now() : null),
                    'period_start' => $stripeInvoice->period_start 
                        ? Carbon::createFromTimestamp($stripeInvoice->period_start) 
                        : null,
                    'period_end' => $stripeInvoice->period_end 
                        ? Carbon::createFromTimestamp($stripeInvoice->period_end) 
                        : null,
                ]
            );

            return $invoice;
        } catch (\Exception $e) {
            Log::error('syncInvoiceFromStripe: Error', [
                'error' => $e->getMessage(),
                'stripe_invoice_id' => $stripeInvoice->id ?? 'unknown',
            ]);
            return null;
        }
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
            Log::error('Failed to send subscription created email', [
                'error' => $e->getMessage(),
                'subscription_id' => $subscription->id,
            ]);
        }
    }

    /**
     * Send subscription cancelled email
     */
    public function sendSubscriptionCancelledEmail(Subscription $subscription): void
    {
        try {
            $user = $subscription->user;
            if ($user && $user->email) {
                Mail::to($user->email)->queue(new SubscriptionCancelledMail($subscription));
                Log::info('Subscription cancelled email queued', ['subscription_id' => $subscription->id]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send subscription cancelled email', [
                'error' => $e->getMessage(),
                'subscription_id' => $subscription->id,
            ]);
        }
    }

    /**
     * Send subscription refunded email
     */
    public function sendSubscriptionRefundedEmail(Subscription $subscription, array $refundData): void
    {
        try {
            $user = $subscription->user;
            if ($user && $user->email) {
                Mail::to($user->email)->queue(new SubscriptionRefundedMail($subscription, $refundData));
                Log::info('Subscription refunded email queued', ['subscription_id' => $subscription->id]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send subscription refunded email', [
                'error' => $e->getMessage(),
                'subscription_id' => $subscription->id,
            ]);
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
            Log::error('Failed to send invoice paid email', [
                'error' => $e->getMessage(),
                'invoice_id' => $invoice->id,
            ]);
        }
    }

    // ==================== Formatting Helpers ====================

    /**
     * Format subscription for API response
     */
    private function formatSubscription(Subscription $subscription): array
    {
        $subscription->loadMissing(['product', 'price', 'invoices']);

        return [
            'id' => $subscription->id,
            'stripe_subscription_id' => $subscription->stripe_subscription_id,
            'status' => $subscription->status,
            'product' => $subscription->product ? [
                'id' => $subscription->product->id,
                'name' => $subscription->product->name,
                'description' => $subscription->product->description,
            ] : null,
            'price' => $subscription->price ? [
                'id' => $subscription->price->id,
                'amount' => $subscription->price->amount,
                'currency' => $subscription->price->currency ?? $subscription->currency,
                'interval' => $subscription->price->interval ?? $subscription->billing_interval,
            ] : [
                'amount' => $subscription->amount,
                'currency' => $subscription->currency,
                'interval' => $subscription->billing_interval,
            ],
            'trial_start' => $subscription->trial_start?->toIso8601String(),
            'trial_end' => $subscription->trial_end?->toIso8601String(),
            'current_period_start' => $subscription->current_period_start?->toIso8601String(),
            'current_period_end' => $subscription->current_period_end?->toIso8601String(),
            'canceled_at' => $subscription->canceled_at?->toIso8601String(),
            'cancel_at_period_end' => $subscription->cancel_at_period_end,
            'can_cancel' => $subscription->isActive(),
            'can_refund' => $subscription->canRefund(),
            'days_until_refund_expires' => $subscription->canRefund() 
                ? max(0, self::REFUND_ELIGIBLE_DAYS - $subscription->created_at->diffInDays(now()))
                : 0,
            'is_trialing' => $subscription->isTrialing(),
            'created_at' => $subscription->created_at->toIso8601String(),
            'invoices' => $subscription->invoices->map(fn($inv) => $this->formatInvoice($inv))->toArray(),
        ];
    }

    /**
     * Format invoice for API response
     */
    private function formatInvoice(Invoice $invoice): array
    {
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
            'due_date' => $invoice->due_date?->toIso8601String(),
            'paid_at' => $invoice->paid_at?->toIso8601String(),
            'period_start' => $invoice->period_start?->toIso8601String(),
            'period_end' => $invoice->period_end?->toIso8601String(),
            'subscription' => $invoice->subscription ? [
                'id' => $invoice->subscription->id,
                'product_name' => $invoice->subscription->product?->name ?? 'Subscription',
            ] : null,
            'created_at' => $invoice->created_at->toIso8601String(),
        ];
    }
}
