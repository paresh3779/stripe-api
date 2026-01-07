<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Stripe\Stripe;
use Stripe\Webhook;
use App\Models\Product;
use App\Models\Price;
use App\Models\Payment;
use App\Models\Coupon;
use App\Models\PromoCode;
use App\Models\StripeCustomer;
use App\Models\StripePaymentMethod;
use App\Models\Subscription;
use App\Models\Invoice;
use App\Constants\StripeEventType;
use App\Constants\PaymentStatus;
use App\Services\Stripe\Subscription\SubscriptionCheckoutService;
use App\Mail\SubscriptionCreatedMail;
use App\Mail\SubscriptionCancelledMail;
use App\Mail\SubscriptionExpiredMail;
use App\Mail\SubscriptionExpirationReminderMail;
use App\Mail\InvoicePaidMail;
use App\Mail\PaymentFailedMail;
use Illuminate\Support\Facades\Mail;

class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $endpoint_secret = config('stripe.webhook_secret');
        $payload = $request->getContent();
        $sig_header = $request->header('stripe-signature');

        try {
            \Log::info('working....');
            $event = Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
        } catch (\Exception $e) {
            \Log::info('webhook error');
            return response('Webhook error', 400);
        }
        
        switch ($event->type) {
            case StripeEventType::CHECKOUT_SESSION_COMPLETED:
                $this->handleCheckoutSessionCompleted($event->data->object);
                break;
            case StripeEventType::PAYMENT_INTENT_SUCCEEDED:
                $this->handlePaymentIntentSucceeded($event->data->object);
                break;
            case StripeEventType::PAYMENT_INTENT_FAILED:
                $this->handlePaymentIntentFailed($event->data->object);
                break;
            case StripeEventType::CHARGE_REFUNDED:
                $this->handleChargeRefunded($event->data->object);
                break;
            case StripeEventType::CHARGE_DISPUTE_CREATED:
                $this->handleChargeDisputeCreated($event->data->object);
                break;
            case StripeEventType::CHARGE_DISPUTE_CLOSED:
                $this->handleChargeDisputeClosed($event->data->object);
                break;
            case StripeEventType::SUBSCRIPTION_TRIAL_WILL_END:
                $this->handleSubscriptionTrialWillEnd($event->data->object);
                break;
            case StripeEventType::SUBSCRIPTION_UPDATED:
                $this->handleSubscriptionUpdated($event->data->object);
                break;
            case StripeEventType::SUBSCRIPTION_CREATED:
                $this->handleSubscriptionCreated($event->data->object);
                break;
            case StripeEventType::SUBSCRIPTION_DELETED:
                $this->handleSubscriptionDeleted($event->data->object);
                break;
            case StripeEventType::PROMOTION_CODE_CREATED:
                $this->handlePromotionCodeCreated($event->data->object);
                break;
            case StripeEventType::PROMOTION_CODE_UPDATED:
                $this->handlePromotionCodeUpdated($event->data->object);
                break;
            case StripeEventType::PROMOTION_CODE_EXPIRED:
                $this->handlePromotionCodeExpired($event->data->object);
                break;
            case StripeEventType::INVOICE_CREATED:
                $this->handleInvoiceCreated($event->data->object);
                break;
            case StripeEventType::INVOICE_FINALIZED:
                $this->handleInvoiceFinalized($event->data->object);
                break;
            case StripeEventType::INVOICE_PAID:
                $this->handleInvoicePaid($event->data->object);
                break;
            case StripeEventType::INVOICE_PAYMENT_FAILED:
                $this->handleInvoicePaymentFailed($event->data->object);
                break;
            default:
                \Log::info('Webhook: Unhandled event type', ['type' => $event->type]);
                break;
        }

        return response('OK');
    }

    private function handleCheckoutSessionCompleted($session)
    {
        \Stripe\Stripe::setApiKey(config('stripe.secret'));

        \Log::info('Webhook: handleCheckoutSessionCompleted', [
            'session_id' => $session->id,
            'payment_status' => $session->payment_status,
            'customer' => $session->customer,
        ]);

        try {
            // Only proceed if payment_intent exists
            if (!$session->payment_intent) {
                \Log::warning('handleCheckoutSessionCompleted: No payment_intent found', [
                    'session_id' => $session->id,
                ]);
                return;
            }

            $paymentIntent = \Stripe\PaymentIntent::retrieve($session->payment_intent);

            \Log::info('handleCheckoutSessionCompleted: PaymentIntent retrieved', [
                'payment_intent_id' => $paymentIntent->id,
                'payment_method' => $paymentIntent->payment_method,
            ]);

            // Only proceed if payment_method exists
            if (!$paymentIntent->payment_method) {
                \Log::warning('handleCheckoutSessionCompleted: No payment_method found', [
                    'payment_intent_id' => $paymentIntent->id,
                ]);
                return;
            }

            $paymentMethod = \Stripe\PaymentMethod::retrieve($paymentIntent->payment_method);

            \Log::info('handleCheckoutSessionCompleted: PaymentMethod retrieved', [
                'payment_method_id' => $paymentMethod->id,
                'type' => $paymentMethod->type,
            ]);

            // Save payment method if it's a card and we have a customer
            if ($paymentMethod->type === 'card' && $session->customer) {
                // Find the StripeCustomer by stripe_customer_id to get the correct customer_id (UUID)
                $stripeCustomer = StripeCustomer::where('stripe_customer_id', $session->customer)->first();

                if ($stripeCustomer) {
                    StripePaymentMethod::updateOrCreate(
                        [
                            'stripe_payment_method_id' => $paymentMethod->id,
                        ],
                        [
                            'customer_id' => $stripeCustomer->id,
                            'type' => $paymentMethod->type,
                            'card_brand' => $paymentMethod->card->brand ?? null,
                            'last4' => $paymentMethod->card->last4 ?? null,
                            'exp_month' => $paymentMethod->card->exp_month ?? null,
                            'exp_year' => $paymentMethod->card->exp_year ?? null,
                            'is_default' => true,
                        ]
                    );

                    \Log::info('handleCheckoutSessionCompleted: StripePaymentMethod saved', [
                        'stripe_payment_method_id' => $paymentMethod->id,
                        'customer_id' => $stripeCustomer->id,
                    ]);
                } else {
                    \Log::warning('handleCheckoutSessionCompleted: StripeCustomer not found', [
                        'stripe_customer_id' => $session->customer,
                    ]);
                }
            }
        } catch (\Exception $e) {
            \Log::error('handleCheckoutSessionCompleted: Error capturing payment method', [
                'error' => $e->getMessage(),
                'session_id' => $session->id,
            ]);
        }

        
        
        if ($session->payment_status == 'paid') {
            $user_id = $session->metadata->user_id ?? null;
            $product_id = $session->metadata->product_id ?? null;
            $price_id = $session->metadata->price_id ?? null;
            $coupon_id = $session->metadata->coupon_id ?? null;
            $promo_code_id = $session->metadata->promo_code_id ?? null;

            Payment::insert([
                'id' => Str::uuid(),
                'user_id' => $user_id,
                'product_id' => $product_id,
                'price_id' => $price_id,
                'stripe_payment_intent_id' => $session->payment_intent,
                'stripe_charge_id' => $session->payment_intent ? null : $session->latest_charge,
                'stripe_invoice_id' => $session->invoice,
                'description' => 'Payment for ' . ($session->metadata->product_name ?? 'product'),
                'amount' => $session->amount_total,
                'currency' => $session->currency,
                //'status' => PaymentStatus::PAID,
                'status' => PaymentStatus::SUCCEEDED,
                'payment_method' => 'stripe',
                'billing_reason' => 'one_time',
                'paid_at' => now(),
                //'coupon_id' => $coupon_id,
                //'promo_code_id' => $promo_code_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function handlePaymentIntentSucceeded($paymentIntent)
    {
        \Log::info('Webhook: handlePaymentIntentSucceeded', [
            'payment_intent_id' => $paymentIntent->id,
            'amount' => $paymentIntent->amount,
            'status' => $paymentIntent->status,
        ]);

        Payment::where('stripe_payment_intent_id', $paymentIntent->id)->update([
            'status' => PaymentStatus::SUCCEEDED,
            'paid_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function handlePaymentIntentFailed($paymentIntent)
    {
        \Log::info('Webhook: handlePaymentIntentFailed', [
            'payment_intent_id' => $paymentIntent->id,
            'last_payment_error' => $paymentIntent->last_payment_error->message ?? null,
        ]);

        Payment::where('stripe_payment_intent_id', $paymentIntent->id)->update([
            'status' => PaymentStatus::FAILED,
            'updated_at' => now(),
        ]);
    }

    private function handleChargeRefunded($charge)
    {
        \Log::info('Webhook: handleChargeRefunded', [
            'charge_id' => $charge->id,
            'amount_refunded' => $charge->amount_refunded,
            'refunded' => $charge->refunded,
        ]);

        // Determine if fully or partially refunded
        $status = $charge->refunded ? PaymentStatus::REFUNDED : PaymentStatus::PARTIALLY_REFUNDED;

        Payment::where('stripe_charge_id', $charge->id)->update([
            'status' => $status,
            'updated_at' => now(),
        ]);
    }

    private function handleChargeDisputeCreated($dispute)
    {
        \Log::info('Webhook: handleChargeDisputeCreated', [
            'dispute_id' => $dispute->id,
            'charge_id' => $dispute->charge,
            'reason' => $dispute->reason,
            'amount' => $dispute->amount,
        ]);

        Payment::where('stripe_charge_id', $dispute->charge)->update([
            'status' => PaymentStatus::DISPUTED,
            'updated_at' => now(),
        ]);
    }

    private function handleChargeDisputeClosed($dispute)
    {
        \Log::info('Webhook: handleChargeDisputeClosed', [
            'dispute_id' => $dispute->id,
            'charge_id' => $dispute->charge,
            'status' => $dispute->status,
        ]);

        // Only mark as succeeded if dispute was won, otherwise keep disputed
        $status = $dispute->status === 'won' ? PaymentStatus::SUCCEEDED : PaymentStatus::DISPUTED;

        Payment::where('stripe_charge_id', $dispute->charge)->update([
            'status' => $status,
            'updated_at' => now(),
        ]);
    }

    private function handleSubscriptionTrialWillEnd($subscription)
    {
        \Log::info('Webhook: handleSubscriptionTrialWillEnd', [
            'subscription_id' => $subscription->id,
            'customer' => $subscription->customer,
            'trial_end' => $subscription->trial_end,
        ]);

        $customer = StripeCustomer::where('stripe_customer_id', $subscription->customer)->first();
        if ($customer) {
            // Send notification
            \Log::info('handleSubscriptionTrialWillEnd: Customer found, notification pending', [
                'user_id' => $customer->user_id,
            ]);
        }
    }

    private function handleSubscriptionUpdated($stripeSubscription)
    {
        \Log::info('Webhook: handleSubscriptionUpdated', [
            'subscription_id' => $stripeSubscription->id,
            'customer' => $stripeSubscription->customer,
            'status' => $stripeSubscription->status,
        ]);

        try {
            $subscriptionService = app(SubscriptionCheckoutService::class);
            $subscription = $subscriptionService->syncSubscriptionFromStripe($stripeSubscription);

            if ($subscription) {
                // Check if subscription is about to expire and send reminder
                if ($subscription->cancel_at_period_end && $subscription->current_period_end) {
                    $daysRemaining = now()->diffInDays($subscription->current_period_end, false);
                    
                    // Send reminder if expiring in 7, 3, or 1 day
                    if (in_array($daysRemaining, [7, 3, 1])) {
                        $user = $subscription->user;
                        if ($user && $user->email) {
                            Mail::to($user->email)->queue(
                                new SubscriptionExpirationReminderMail($subscription, $daysRemaining)
                            );
                            \Log::info('handleSubscriptionUpdated: Expiration reminder queued', [
                                'subscription_id' => $subscription->id,
                                'days_remaining' => $daysRemaining,
                            ]);
                        }
                    }
                }

                \Log::info('handleSubscriptionUpdated: Subscription synced', [
                    'subscription_id' => $subscription->id,
                    'status' => $subscription->status,
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('handleSubscriptionUpdated: Error', [
                'error' => $e->getMessage(),
                'subscription_id' => $stripeSubscription->id,
            ]);
        }
    }

    private function handlePromotionCodeCreated($promotionCode)
    {
        \Log::info('Webhook: handlePromotionCodeCreated', [
            'promotion_code_id' => $promotionCode->id,
            'code' => $promotionCode->code,
            'active' => $promotionCode->active,
        ]);

        PromoCode::updateOrCreate(
            ['stripe_promotion_code_id' => $promotionCode->id],
            [
                'code' => $promotionCode->code,
                'active' => $promotionCode->active,
            ]
        );
    }

    private function handlePromotionCodeUpdated($promotionCode)
    {
        \Log::info('Webhook: handlePromotionCodeUpdated', [
            'promotion_code_id' => $promotionCode->id,
            'code' => $promotionCode->code,
            'active' => $promotionCode->active,
        ]);

        PromoCode::where('stripe_promotion_code_id', $promotionCode->id)->update([
            'active' => $promotionCode->active,
            'updated_at' => now(),
        ]);
    }

    private function handlePromotionCodeExpired($promotionCode)
    {
        \Log::info('Webhook: handlePromotionCodeExpired', [
            'promotion_code_id' => $promotionCode->id,
            'code' => $promotionCode->code,
        ]);

        PromoCode::where('stripe_promotion_code_id', $promotionCode->id)->update([
            'active' => false,
            'updated_at' => now(),
        ]);
    }

    private function handleInvoicePaid($stripeInvoice)
    {
        \Log::info('Webhook: handleInvoicePaid', [
            'invoice_id' => $stripeInvoice->id,
            'customer' => $stripeInvoice->customer,
            'amount_paid' => $stripeInvoice->amount_paid,
        ]);

        try {
            // Sync invoice to database
            $subscriptionService = app(SubscriptionCheckoutService::class);
            $invoice = $subscriptionService->syncInvoiceFromStripe($stripeInvoice);

            if ($invoice) {
                // Send invoice paid email
                $user = $invoice->user;
                if ($user && $user->email) {
                    Mail::to($user->email)->queue(new InvoicePaidMail($invoice));
                    \Log::info('handleInvoicePaid: Email queued', [
                        'invoice_id' => $invoice->id,
                    ]);
                }
            }

            // Handle promo code redemptions
            if ($stripeInvoice->discounts) {
                foreach ($stripeInvoice->discounts as $discount) {
                    PromoCode::where('stripe_promotion_code_id', $discount->promotion_code)->increment('times_redeemed');
                }
            }
        } catch (\Exception $e) {
            \Log::error('handleInvoicePaid: Error', [
                'error' => $e->getMessage(),
                'invoice_id' => $stripeInvoice->id,
            ]);
        }
    }

    /**
     * Handle invoice payment failed event
     *
     * @param object $invoice
     * @return void
     */
    private function handleInvoicePaymentFailed($stripeInvoice)
    {
        \Log::info('Webhook: handleInvoicePaymentFailed', [
            'invoice_id' => $stripeInvoice->id,
            'customer' => $stripeInvoice->customer,
            'attempt_count' => $stripeInvoice->attempt_count ?? null,
        ]);

        try {
            // Sync invoice to database
            $subscriptionService = app(SubscriptionCheckoutService::class);
            $invoice = $subscriptionService->syncInvoiceFromStripe($stripeInvoice);

            if ($invoice) {
                // Get related subscription if any
                $subscription = $invoice->subscription;

                // Send payment failed email
                $user = $invoice->user;
                if ($user && $user->email) {
                    Mail::to($user->email)->queue(new PaymentFailedMail($invoice, $subscription));
                    \Log::info('handleInvoicePaymentFailed: Email queued', [
                        'invoice_id' => $invoice->id,
                    ]);
                }
            }

            // Update related payments
            Payment::where('stripe_invoice_id', $stripeInvoice->id)->update([
                'status' => PaymentStatus::FAILED,
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            \Log::error('handleInvoicePaymentFailed: Error', [
                'error' => $e->getMessage(),
                'invoice_id' => $stripeInvoice->id,
            ]);
        }
    }

    /**
     * Handle subscription created event
     *
     * @param object $subscription
     * @return void
     */
    private function handleSubscriptionCreated($stripeSubscription)
    {
        \Log::info('Webhook: handleSubscriptionCreated', [
            'subscription_id' => $stripeSubscription->id,
            'customer' => $stripeSubscription->customer,
            'status' => $stripeSubscription->status,
        ]);

        try {
            $subscriptionService = app(SubscriptionCheckoutService::class);
            $subscription = $subscriptionService->syncSubscriptionFromStripe($stripeSubscription);

            if ($subscription) {
                // Send subscription created email
                $user = $subscription->user;
                if ($user && $user->email) {
                    Mail::to($user->email)->queue(new SubscriptionCreatedMail($subscription));
                    \Log::info('handleSubscriptionCreated: Email queued', [
                        'subscription_id' => $subscription->id,
                        'user_id' => $user->id,
                    ]);
                }

                \Log::info('handleSubscriptionCreated: Subscription synced', [
                    'subscription_id' => $subscription->id,
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('handleSubscriptionCreated: Error', [
                'error' => $e->getMessage(),
                'subscription_id' => $stripeSubscription->id,
            ]);
        }
    }

    /**
     * Handle subscription deleted/cancelled event
     *
     * @param object $subscription
     * @return void
     */
    private function handleSubscriptionDeleted($stripeSubscription)
    {
        \Log::info('Webhook: handleSubscriptionDeleted', [
            'subscription_id' => $stripeSubscription->id,
            'customer' => $stripeSubscription->customer,
            'canceled_at' => $stripeSubscription->canceled_at ?? null,
        ]);

        try {
            $subscription = Subscription::where('stripe_subscription_id', $stripeSubscription->id)->first();

            if ($subscription) {
                $subscription->update([
                    'status' => 'canceled',
                    'canceled_at' => $stripeSubscription->canceled_at 
                        ? \Carbon\Carbon::createFromTimestamp($stripeSubscription->canceled_at) 
                        : now(),
                    'ended_at' => now(),
                ]);

                // Send subscription expired email
                $user = $subscription->user;
                if ($user && $user->email) {
                    Mail::to($user->email)->queue(new SubscriptionExpiredMail($subscription));
                    \Log::info('handleSubscriptionDeleted: Expired email queued', [
                        'subscription_id' => $subscription->id,
                    ]);
                }
            }

            // Update related payments
            Payment::where('stripe_subscription_id', $stripeSubscription->id)->update([
                'status' => PaymentStatus::CANCELLED,
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            \Log::error('handleSubscriptionDeleted: Error', [
                'error' => $e->getMessage(),
                'subscription_id' => $stripeSubscription->id,
            ]);
        }
    }

    /**
     * Handle invoice created event
     *
     * @param object $invoice
     * @return void
     */
    private function handleInvoiceCreated($stripeInvoice)
    {
        \Log::info('Webhook: handleInvoiceCreated', [
            'invoice_id' => $stripeInvoice->id,
            'customer' => $stripeInvoice->customer,
            'status' => $stripeInvoice->status,
        ]);

        try {
            $subscriptionService = app(SubscriptionCheckoutService::class);
            $invoice = $subscriptionService->syncInvoiceFromStripe($stripeInvoice);

            if ($invoice) {
                \Log::info('handleInvoiceCreated: Invoice synced', [
                    'invoice_id' => $invoice->id,
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('handleInvoiceCreated: Error', [
                'error' => $e->getMessage(),
                'invoice_id' => $stripeInvoice->id,
            ]);
        }
    }

    /**
     * Handle invoice finalized event
     *
     * @param object $invoice
     * @return void
     */
    private function handleInvoiceFinalized($stripeInvoice)
    {
        \Log::info('Webhook: handleInvoiceFinalized', [
            'invoice_id' => $stripeInvoice->id,
            'customer' => $stripeInvoice->customer,
            'amount_due' => $stripeInvoice->amount_due,
        ]);

        try {
            $subscriptionService = app(SubscriptionCheckoutService::class);
            $subscriptionService->syncInvoiceFromStripe($stripeInvoice);
        } catch (\Exception $e) {
            \Log::error('handleInvoiceFinalized: Error', [
                'error' => $e->getMessage(),
                'invoice_id' => $stripeInvoice->id,
            ]);
        }
    }
}
