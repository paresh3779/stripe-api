<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Subscription PaymentIntent related messages and validation constants
 */
class SubscriptionPaymentIntentMessages
{
    // Success Messages
    public const PAYMENT_INTENT_CREATED = 'Subscription payment intent created successfully';
    public const SUBSCRIPTION_CREATED = 'Subscription created successfully';
    public const PAYMENT_CONFIRMED = 'Payment confirmed successfully';
    public const TRIAL_STARTED = 'Trial period started successfully';
    public const PROMO_CODE_APPLIED = 'Promo code applied successfully';
    public const COUPON_APPLIED = 'Coupon applied successfully';

    // Error Messages - Products
    public const PRODUCT_NOT_FOUND = 'Subscription product not found';
    public const PRODUCT_NOT_AVAILABLE = 'Subscription product is not available';
    public const PRODUCT_NOT_RECURRING = 'This product does not support recurring billing';

    // Error Messages - Prices
    public const PRICE_NOT_FOUND = 'Subscription price not found';
    public const PRICE_NOT_ACTIVE = 'This subscription price is not active';
    public const PRICE_NOT_RECURRING = 'This price is not for recurring subscription';

    // Error Messages - Trial
    public const TRIAL_NOT_AVAILABLE = 'Trial period is not available for this plan';
    public const TRIAL_ALREADY_USED = 'You have already used your trial period';

    // Error Messages - Promo Codes
    public const PROMO_CODE_INVALID = 'Invalid promo code';
    public const PROMO_CODE_NOT_ACTIVE = 'Promo code is not active';
    public const PROMO_CODE_EXPIRED = 'Promo code has expired';

    // Error Messages - Coupons
    public const COUPON_INVALID = 'Invalid coupon';
    public const COUPON_NOT_ACTIVE = 'Coupon is not active';
    public const COUPON_EXPIRED = 'Coupon has expired';

    // Error Messages - Payment
    public const PAYMENT_INTENT_CREATION_FAILED = 'Failed to create payment intent';
    public const PAYMENT_INTENT_RETRIEVAL_FAILED = 'Failed to retrieve payment intent';
    public const PAYMENT_RECORD_NOT_FOUND = 'Payment record not found';
    public const SUBSCRIPTION_CREATION_FAILED = 'Failed to create subscription';

    // Error Messages - Customer
    public const CUSTOMER_NOT_FOUND = 'Customer not found';
    public const CUSTOMER_CREATION_FAILED = 'Failed to create customer';

    // Error Messages - User
    public const USER_NOT_AUTHENTICATED = 'User not authenticated';

    // Validation Messages
    public const PRICE_ID_REQUIRED = 'Price ID is required';
    public const PAYMENT_METHOD_ID_REQUIRED = 'Payment method ID is required';
    public const PAYMENT_INTENT_ID_REQUIRED = 'Payment intent ID is required';
}
