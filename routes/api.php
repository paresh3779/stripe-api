<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Stripe\CheckoutController;
use App\Http\Controllers\Api\Stripe\CheckoutWithPromoCodeController;
use App\Http\Controllers\Api\Stripe\CheckoutWithCouponController;
use App\Http\Controllers\Api\Stripe\PaymentIntentController;
use App\Http\Controllers\Api\Stripe\PaymentIntentWithPromoCodeController;
use App\Http\Controllers\Api\Stripe\PaymentIntentWithCouponController;
use App\Http\Controllers\Api\Stripe\Subscription\SubscriptionCheckoutController;
use App\Http\Controllers\Api\Stripe\Subscription\SubscriptionTrialCheckoutController;
use App\Http\Controllers\Api\Stripe\Subscription\SubscriptionCouponCheckoutController;
use App\Http\Controllers\Api\Stripe\Subscription\SubscriptionPromoCodeCheckoutController;
use App\Http\Controllers\Api\Stripe\SubscriptionPaymentIntent\SubscriptionPaymentIntentController;
use App\Http\Controllers\Api\Stripe\SubscriptionPaymentIntent\SubscriptionTrialPaymentIntentController;
use App\Http\Controllers\Api\Stripe\SubscriptionPaymentIntent\SubscriptionCouponPaymentIntentController;
use App\Http\Controllers\Api\Stripe\SubscriptionPaymentIntent\SubscriptionPromoCodePaymentIntentController;
use App\Http\Controllers\Api\Stripe\InvoiceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StripeWebhookController;

// Auth routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

Route::middleware([
    'cookie.token',
    'sanctum.rotate',
    'sanitize',
])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    //Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Stripe Checkout Routes - with rate limiting
    Route::prefix('stripe/checkout')->middleware(['stripe.rate_limit:default'])->group(function () {
        // Basic Checkout (without promo code/coupon)
        Route::prefix('basic')->group(function () {
            Route::get('/products', [CheckoutController::class, 'getProducts']);
            Route::get('/products/{productId}', [CheckoutController::class, 'getProduct']);
            Route::post('/create-session', [CheckoutController::class, 'createCheckoutSession']);
            Route::post('/verify-session', [CheckoutController::class, 'verifySession']);
        });

        // Checkout with Promo Code
        Route::prefix('promocode')->group(function () {
            Route::get('/products', [CheckoutWithPromoCodeController::class, 'getProducts']);
            Route::get('/products/{productId}', [CheckoutWithPromoCodeController::class, 'getProduct']);
            Route::post('/validate-promocode', [CheckoutWithPromoCodeController::class, 'validatePromoCode']);
            Route::post('/create-session', [CheckoutWithPromoCodeController::class, 'createCheckoutSession']);
        });

        // Checkout with Coupon
        Route::prefix('coupon')->group(function () {
            Route::get('/products', [CheckoutWithCouponController::class, 'getProducts']);
            Route::get('/products/{productId}', [CheckoutWithCouponController::class, 'getProduct']);
            Route::get('/coupons', [CheckoutWithCouponController::class, 'getCoupons']);
            Route::post('/create-session', [CheckoutWithCouponController::class, 'createCheckoutSession']);
        });
    });

    // Stripe PaymentIntent Routes - with strict rate limiting for payment operations
    Route::prefix('stripe/payment-intent')->middleware(['stripe.rate_limit:strict'])->group(function () {
        // Basic PaymentIntent (without promo code/coupon)
        Route::prefix('basic')->group(function () {
            Route::get('/products', [PaymentIntentController::class, 'getProducts']);
            Route::get('/products/{productId}', [PaymentIntentController::class, 'getProduct']);
            Route::post('/create', [PaymentIntentController::class, 'createPaymentIntent']);
            Route::post('/confirm', [PaymentIntentController::class, 'confirmPayment']);
        });

        // PaymentIntent with Promo Code
        Route::prefix('promocode')->group(function () {
            Route::get('/products', [PaymentIntentWithPromoCodeController::class, 'getProducts']);
            Route::get('/products/{productId}', [PaymentIntentWithPromoCodeController::class, 'getProduct']);
            Route::post('/validate-promocode', [PaymentIntentWithPromoCodeController::class, 'validatePromoCode']);
            Route::post('/create', [PaymentIntentWithPromoCodeController::class, 'createPaymentIntent']);
            Route::post('/confirm', [PaymentIntentWithPromoCodeController::class, 'confirmPayment']);
        });

        // PaymentIntent with Coupon
        Route::prefix('coupon')->group(function () {
            Route::get('/products', [PaymentIntentWithCouponController::class, 'getProducts']);
            Route::get('/products/{productId}', [PaymentIntentWithCouponController::class, 'getProduct']);
            Route::get('/coupons', [PaymentIntentWithCouponController::class, 'getCoupons']);
            Route::post('/validate-coupon', [PaymentIntentWithCouponController::class, 'validateCoupon']);
            Route::post('/create', [PaymentIntentWithCouponController::class, 'createPaymentIntent']);
            Route::post('/confirm', [PaymentIntentWithCouponController::class, 'confirmPayment']);
        });
    });

    // Stripe Subscription Checkout Routes - with rate limiting
    Route::prefix('stripe/subscription-checkout')->middleware(['stripe.rate_limit:default'])->group(function () {
        // Demo 1: Basic Subscription (Monthly/Yearly) with full management
        Route::prefix('subscription')->group(function () {
            // Products
            Route::get('/products', [SubscriptionCheckoutController::class, 'getProducts']);
            Route::get('/products/{productId}', [SubscriptionCheckoutController::class, 'getProduct']);
            
            // Checkout
            Route::post('/create-session', [SubscriptionCheckoutController::class, 'createCheckoutSession']);
            
            // Subscription Management
            Route::get('/subscriptions', [SubscriptionCheckoutController::class, 'getSubscriptions']);
            Route::get('/subscriptions/{subscriptionId}', [SubscriptionCheckoutController::class, 'getSubscription']);
            Route::post('/subscriptions/{subscriptionId}/cancel', [SubscriptionCheckoutController::class, 'cancelSubscription']);
            
            // Invoice Management
            Route::get('/invoices', [SubscriptionCheckoutController::class, 'getInvoices']);
            Route::get('/invoices/{invoiceId}', [SubscriptionCheckoutController::class, 'getInvoice']);
            Route::get('/invoices/{invoiceId}/download', [SubscriptionCheckoutController::class, 'downloadInvoice']);
        });

        // Demo 2: Subscription with Trial Period
        Route::prefix('trial')->group(function () {
            Route::get('/products', [SubscriptionTrialCheckoutController::class, 'getProducts']);
            Route::get('/products/{productId}', [SubscriptionTrialCheckoutController::class, 'getProduct']);
            Route::post('/trial-info', [SubscriptionTrialCheckoutController::class, 'getTrialInfo']);
            Route::post('/create-session', [SubscriptionTrialCheckoutController::class, 'createCheckoutSession']);
        });

        // Demo 3: Subscription with Coupon
        Route::prefix('coupon')->group(function () {
            Route::get('/products', [SubscriptionCouponCheckoutController::class, 'getProducts']);
            Route::get('/products/{productId}', [SubscriptionCouponCheckoutController::class, 'getProduct']);
            Route::get('/coupons', [SubscriptionCouponCheckoutController::class, 'getCoupons']);
            Route::post('/validate-coupon', [SubscriptionCouponCheckoutController::class, 'validateCoupon']);
            Route::post('/calculate-discount', [SubscriptionCouponCheckoutController::class, 'calculateDiscount']);
            Route::post('/create-session', [SubscriptionCouponCheckoutController::class, 'createCheckoutSession']);
        });

        // Demo 4: Subscription with Promo Code
        Route::prefix('promocode')->group(function () {
            Route::get('/products', [SubscriptionPromoCodeCheckoutController::class, 'getProducts']);
            Route::get('/products/{productId}', [SubscriptionPromoCodeCheckoutController::class, 'getProduct']);
            Route::post('/validate-promocode', [SubscriptionPromoCodeCheckoutController::class, 'validatePromoCode']);
            Route::post('/calculate-discount', [SubscriptionPromoCodeCheckoutController::class, 'calculateDiscount']);
            Route::post('/create-session', [SubscriptionPromoCodeCheckoutController::class, 'createCheckoutSession']);
        });
    });

    // Stripe Subscription PaymentIntent Routes - with strict rate limiting
    Route::prefix('stripe/subscription-payment-intent')->middleware(['stripe.rate_limit:strict'])->group(function () {
        // Demo 1: Basic Subscription (Monthly/Yearly)
        Route::prefix('subscription')->group(function () {
            Route::get('/products', [SubscriptionPaymentIntentController::class, 'getProducts']);
            Route::get('/products/{productId}', [SubscriptionPaymentIntentController::class, 'getProduct']);
            Route::post('/setup-intent', [SubscriptionPaymentIntentController::class, 'createSetupIntent']);
            Route::post('/create', [SubscriptionPaymentIntentController::class, 'createSubscription']);
            Route::post('/confirm', [SubscriptionPaymentIntentController::class, 'confirmSubscription']);
        });

        // Demo 2: Subscription with 15-day Trial Period (Full Management)
        Route::prefix('trial')->group(function () {
            // Products
            Route::get('/products', [SubscriptionTrialPaymentIntentController::class, 'getProducts']);
            Route::get('/products/{productId}', [SubscriptionTrialPaymentIntentController::class, 'getProduct']);
            Route::post('/trial-info', [SubscriptionTrialPaymentIntentController::class, 'getTrialInfo']);
            
            // Payment Methods
            Route::get('/payment-methods', [SubscriptionTrialPaymentIntentController::class, 'getPaymentMethods']);
            Route::post('/payment-methods', [SubscriptionTrialPaymentIntentController::class, 'savePaymentMethod']);
            Route::delete('/payment-methods/{paymentMethodId}', [SubscriptionTrialPaymentIntentController::class, 'deletePaymentMethod']);
            
            // Checkout
            Route::post('/setup-intent', [SubscriptionTrialPaymentIntentController::class, 'createSetupIntent']);
            Route::post('/create', [SubscriptionTrialPaymentIntentController::class, 'createSubscription']);
            Route::post('/create-with-saved', [SubscriptionTrialPaymentIntentController::class, 'createSubscriptionWithSavedMethod']);
            Route::post('/confirm', [SubscriptionTrialPaymentIntentController::class, 'confirmSubscription']);
            
            // Subscription Management
            Route::get('/subscriptions', [SubscriptionTrialPaymentIntentController::class, 'getSubscriptions']);
            Route::get('/subscriptions/{subscriptionId}', [SubscriptionTrialPaymentIntentController::class, 'getSubscription']);
            Route::post('/subscriptions/{subscriptionId}/cancel', [SubscriptionTrialPaymentIntentController::class, 'cancelSubscription']);
            
            // Invoice Management
            Route::get('/invoices', [SubscriptionTrialPaymentIntentController::class, 'getInvoices']);
            Route::get('/invoices/{invoiceId}', [SubscriptionTrialPaymentIntentController::class, 'getInvoice']);
            Route::get('/invoices/{invoiceId}/download', [SubscriptionTrialPaymentIntentController::class, 'downloadInvoice']);
        });

        // Demo 3: Subscription with Coupon
        Route::prefix('coupon')->group(function () {
            Route::get('/products', [SubscriptionCouponPaymentIntentController::class, 'getProducts']);
            Route::get('/products/{productId}', [SubscriptionCouponPaymentIntentController::class, 'getProduct']);
            Route::get('/coupons', [SubscriptionCouponPaymentIntentController::class, 'getCoupons']);
            Route::post('/validate-coupon', [SubscriptionCouponPaymentIntentController::class, 'validateCoupon']);
            Route::post('/calculate-discount', [SubscriptionCouponPaymentIntentController::class, 'calculateDiscount']);
            Route::post('/setup-intent', [SubscriptionCouponPaymentIntentController::class, 'createSetupIntent']);
            Route::post('/create', [SubscriptionCouponPaymentIntentController::class, 'createSubscription']);
            Route::post('/confirm', [SubscriptionCouponPaymentIntentController::class, 'confirmSubscription']);
        });

        // Demo 4: Subscription with Promo Code
        Route::prefix('promocode')->group(function () {
            Route::get('/products', [SubscriptionPromoCodePaymentIntentController::class, 'getProducts']);
            Route::get('/products/{productId}', [SubscriptionPromoCodePaymentIntentController::class, 'getProduct']);
            Route::post('/validate-promocode', [SubscriptionPromoCodePaymentIntentController::class, 'validatePromoCode']);
            Route::post('/calculate-discount', [SubscriptionPromoCodePaymentIntentController::class, 'calculateDiscount']);
            Route::post('/setup-intent', [SubscriptionPromoCodePaymentIntentController::class, 'createSetupIntent']);
            Route::post('/create', [SubscriptionPromoCodePaymentIntentController::class, 'createSubscription']);
            Route::post('/confirm', [SubscriptionPromoCodePaymentIntentController::class, 'confirmSubscription']);
        });
    });

    // ==================== Centralized Invoice Management ====================
    Route::prefix('stripe/invoices')->middleware(['stripe.rate_limit:default'])->group(function () {
        Route::get('/', [InvoiceController::class, 'index']);
        Route::get('/statistics', [InvoiceController::class, 'statistics']);
        Route::post('/sync', [InvoiceController::class, 'syncFromStripe']);
        Route::get('/{invoiceId}', [InvoiceController::class, 'show']);
        Route::get('/{invoiceId}/download', [InvoiceController::class, 'downloadPdf']);
        Route::get('/{invoiceId}/view-on-stripe', [InvoiceController::class, 'viewOnStripe']);
        Route::get('/{invoiceId}/print', [InvoiceController::class, 'printData']);
        Route::post('/{invoiceId}/resend-email', [InvoiceController::class, 'resendEmail']);
    });
});


// Stripe Webhook - with signature validation (no auth required)
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle'])
    ->middleware(['stripe.signature']);
