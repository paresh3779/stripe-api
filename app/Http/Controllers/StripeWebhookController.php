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

class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $endpoint_secret = env('STRIPE_WEBHOOK_SECRET');
        $payload = $request->getContent();
        $sig_header = $request->header('Stripe-Signature');

        try {
            $event = Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
        } catch (\Exception $e) {
            return response('Webhook error', 400);
        }

        switch ($event->type) {
            case 'checkout.session.completed':
                $this->handleCheckoutSessionCompleted($event->data->object);
                break;
            case 'payment_intent.succeeded':
                $this->handlePaymentIntentSucceeded($event->data->object);
                break;
            case 'payment_intent.payment_failed':
                $this->handlePaymentIntentFailed($event->data->object);
                break;
            case 'charge.refunded':
                $this->handleChargeRefunded($event->data->object);
                break;
            case 'charge.dispute.created':
                $this->handleChargeDisputeCreated($event->data->object);
                break;
            case 'charge.dispute.closed':
                $this->handleChargeDisputeClosed($event->data->object);
                break;
            case 'customer.subscription.trial_will_end':
                $this->handleSubscriptionTrialWillEnd($event->data->object);
                break;
            case 'customer.subscription.updated':
                $this->handleSubscriptionUpdated($event->data->object);
                break;
            case 'promotion_code.created':
                $this->handlePromotionCodeCreated($event->data->object);
                break;
            case 'promotion_code.updated':
                $this->handlePromotionCodeUpdated($event->data->object);
                break;
            case 'promotion_code.expired':
                $this->handlePromotionCodeExpired($event->data->object);
                break;
            case 'invoice.paid':
                $this->handleInvoicePaid($event->data->object);
                break;
            default:
                // Unhandled event type
                break;
        }

        return response('OK');
    }

    private function handleCheckoutSessionCompleted($session)
    {
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
                'status' => 'paid',
                'payment_method' => 'stripe',
                'billing_reason' => 'subscription_cycle',
                'paid_at' => now(),
                'coupon_id' => $coupon_id,
                'promo_code_id' => $promo_code_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function handlePaymentIntentSucceeded($paymentIntent)
    {
        Payment::where('stripe_payment_intent_id', $paymentIntent->id)->update([
            'status' => 'paid',
            'paid_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function handlePaymentIntentFailed($paymentIntent)
    {
        Payment::where('stripe_payment_intent_id', $paymentIntent->id)->update([
            'status' => 'failed',
            'updated_at' => now(),
        ]);
    }

    private function handleChargeRefunded($charge)
    {
        Payment::where('stripe_charge_id', $charge->id)->update([
            'status' => 'refunded',
            'updated_at' => now(),
        ]);
    }

    private function handleChargeDisputeCreated($dispute)
    {
        Payment::where('stripe_charge_id', $dispute->charge)->update([
            'status' => 'disputed',
            'updated_at' => now(),
        ]);
    }

    private function handleChargeDisputeClosed($dispute)
    {
        Payment::where('stripe_charge_id', $dispute->charge)->update([
            'status' => 'paid',
            'updated_at' => now(),
        ]);
    }

    private function handleSubscriptionTrialWillEnd($subscription)
    {
        $customer = StripeCustomer::where('stripe_customer_id', $subscription->customer)->first();
        if ($customer) {
            // Send notification
        }
    }

    private function handleSubscriptionUpdated($subscription)
    {
        // Update subscription status
    }

    private function handlePromotionCodeCreated($promotionCode)
    {
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
        PromoCode::where('stripe_promotion_code_id', $promotionCode->id)->update([
            'active' => $promotionCode->active,
            'updated_at' => now(),
        ]);
    }

    private function handlePromotionCodeExpired($promotionCode)
    {
        PromoCode::where('stripe_promotion_code_id', $promotionCode->id)->update([
            'active' => false,
            'updated_at' => now(),
        ]);
    }

    private function handleInvoicePaid($invoice)
    {
        if ($invoice->discounts) {
            foreach ($invoice->discounts as $discount) {
                PromoCode::where('stripe_promotion_code_id', $discount->promotion_code)->increment('times_redeemed');
            }
        }
    }
}
