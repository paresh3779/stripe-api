<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Stripe\CheckoutController;
use App\Http\Controllers\Api\Stripe\CheckoutWithPromoCodeController;
use App\Http\Controllers\Api\Stripe\CheckoutWithCouponController;
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
});


Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle']);
