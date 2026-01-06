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
use App\Constants\StripeEventType;
use App\Constants\PaymentStatus;

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
            case StripeEventType::PROMOTION_CODE_CREATED:
                $this->handlePromotionCodeCreated($event->data->object);
                break;
            case StripeEventType::PROMOTION_CODE_UPDATED:
                $this->handlePromotionCodeUpdated($event->data->object);
                break;
            case StripeEventType::PROMOTION_CODE_EXPIRED:
                $this->handlePromotionCodeExpired($event->data->object);
                break;
            case StripeEventType::INVOICE_PAID:
                $this->handleInvoicePaid($event->data->object);
                break;
            case StripeEventType::INVOICE_PAYMENT_FAILED:
                $this->handleInvoicePaymentFailed($event->data->object);
                break;
            case StripeEventType::SUBSCRIPTION_CREATED:
                $this->handleSubscriptionCreated($event->data->object);
                break;
            case StripeEventType::SUBSCRIPTION_DELETED:
                $this->handleSubscriptionDeleted($event->data->object);
                break;
            default:
                // Unhandled event type
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

    private function handleSubscriptionUpdated($subscription)
    {
        \Log::info('Webhook: handleSubscriptionUpdated', [
            'subscription_id' => $subscription->id,
            'customer' => $subscription->customer,
            'status' => $subscription->status,
        ]);

        // Update subscription status
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

    private function handleInvoicePaid($invoice)
    {
        \Log::info('Webhook: handleInvoicePaid', [
            'invoice_id' => $invoice->id,
            'customer' => $invoice->customer,
            'amount_paid' => $invoice->amount_paid,
        ]);

        if ($invoice->discounts) {
            foreach ($invoice->discounts as $discount) {
                PromoCode::where('stripe_promotion_code_id', $discount->promotion_code)->increment('times_redeemed');
            }
        }
    }

    /**
     * Handle invoice payment failed event
     *
     * @param object $invoice
     * @return void
     */
    private function handleInvoicePaymentFailed($invoice)
    {
        \Log::info('Webhook: handleInvoicePaymentFailed', [
            'invoice_id' => $invoice->id,
            'customer' => $invoice->customer,
            'attempt_count' => $invoice->attempt_count ?? null,
        ]);

        Payment::where('stripe_invoice_id', $invoice->id)->update([
            'status' => PaymentStatus::FAILED,
            'updated_at' => now(),
        ]);
    }

    /**
     * Handle subscription created event
     *
     * @param object $subscription
     * @return void
     */
    private function handleSubscriptionCreated($subscription)
    {
        \Log::info('Webhook: handleSubscriptionCreated', [
            'subscription_id' => $subscription->id,
            'customer' => $subscription->customer,
            'status' => $subscription->status,
        ]);

        $customer = StripeCustomer::where('stripe_customer_id', $subscription->customer)->first();
        
        if ($customer) {
            Payment::insert([
                'id' => Str::uuid(),
                'user_id' => $customer->user_id,
                'stripe_subscription_id' => $subscription->id,
                'description' => 'Subscription created',
                'amount' => $subscription->items->data[0]->price->unit_amount ?? 0,
                'currency' => $subscription->currency,
                'status' => $subscription->status === 'active' ? PaymentStatus::SUCCEEDED : PaymentStatus::PENDING,
                'payment_method' => 'stripe',
                'billing_reason' => 'subscription_create',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            \Log::info('handleSubscriptionCreated: Payment record created', [
                'user_id' => $customer->user_id,
                'subscription_id' => $subscription->id,
            ]);
        } else {
            \Log::warning('handleSubscriptionCreated: StripeCustomer not found', [
                'stripe_customer_id' => $subscription->customer,
            ]);
        }
    }

    /**
     * Handle subscription deleted/cancelled event
     *
     * @param object $subscription
     * @return void
     */
    private function handleSubscriptionDeleted($subscription)
    {
        \Log::info('Webhook: handleSubscriptionDeleted', [
            'subscription_id' => $subscription->id,
            'customer' => $subscription->customer,
            'canceled_at' => $subscription->canceled_at ?? null,
        ]);

        Payment::where('stripe_subscription_id', $subscription->id)->update([
            'status' => PaymentStatus::CANCELLED,
            'updated_at' => now(),
        ]);
    }
}
