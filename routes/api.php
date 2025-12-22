<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Stripe\CheckoutController;
use App\Http\Controllers\Api\Stripe\CheckoutWithPromoCodeController;
use App\Http\Controllers\Api\Stripe\CheckoutWithCouponController;
use App\Http\Controllers\Api\Stripe\PaymentIntentController;
use App\Http\Controllers\Api\Stripe\PaymentIntentWithPromoCodeController;
use App\Http\Controllers\Api\Stripe\PaymentIntentWithCouponController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\StripeWebhookController;

// Auth routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

Route::middleware([
    'cookie.token',
    'sanctum.rotate',
])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    //Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Stripe Checkout Routes
    Route::prefix('stripe/checkout')->group(function () {
        // Basic Checkout (without promo code/coupon)
        Route::prefix('basic')->group(function () {
            Route::get('/products', [CheckoutController::class, 'getProducts']);
            Route::get('/products/{productId}', [CheckoutController::class, 'getProduct']);
            Route::post('/create-session', [CheckoutController::class, 'createCheckoutSession']);
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

    // Stripe PaymentIntent Routes
    Route::prefix('stripe/payment-intent')->group(function () {
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
});


Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle']);
