<?php

namespace App\Constants;

class Messages
{
    // Success Messages
    public const PAYMENT_INTENT_CREATED = 'Payment intent created successfully';
    public const PAYMENT_CONFIRMED = 'Payment confirmed successfully';
    public const PAYMENT_SUCCESSFUL = 'Payment completed successfully';
    public const PROMO_CODE_APPLIED = 'Promo code applied successfully';
    public const COUPON_APPLIED = 'Coupon applied successfully';
    public const PROMO_CODE_VALID = 'Promo code is valid';
    public const COUPON_VALID = 'Coupon is valid';

    // Error Messages - Products
    public const PRODUCT_NOT_FOUND = 'Product not found';
    public const PRODUCT_NOT_AVAILABLE = 'Product is not available';
    public const PRODUCT_INACTIVE = 'Product is inactive';
    public const PRODUCT_ARCHIVED = 'Product is archived';

    // Error Messages - Prices
    public const PRICE_NOT_FOUND = 'Price not found';
    public const PRICE_NOT_ACTIVE = 'This price is not active';
    public const PRICE_NOT_ONE_TIME = 'This price is not for one-time payment';

    // Error Messages - Promo Codes
    public const PROMO_CODE_INVALID = 'Invalid promo code';
    public const PROMO_CODE_NOT_ACTIVE = 'Promo code is not active';
    public const PROMO_CODE_NOT_YET_VALID = 'Promo code is not yet valid';
    public const PROMO_CODE_EXPIRED = 'Promo code has expired';
    public const PROMO_CODE_MAX_REDEMPTIONS = 'Promo code has reached maximum redemptions';
    public const PROMO_CODE_COUPON_INACTIVE = 'Associated coupon is not active';

    // Error Messages - Coupons
    public const COUPON_INVALID = 'Invalid coupon';
    public const COUPON_NOT_ACTIVE = 'Coupon is not active';
    public const COUPON_NOT_YET_VALID = 'Coupon is not yet valid';
    public const COUPON_EXPIRED = 'Coupon has expired';
    public const COUPON_MAX_REDEMPTIONS = 'Coupon has reached maximum redemptions';

    // Error Messages - Payment
    public const PAYMENT_INTENT_CREATION_FAILED = 'Failed to create payment intent';
    public const PAYMENT_INTENT_RETRIEVAL_FAILED = 'Failed to retrieve payment intent';
    public const PAYMENT_RECORD_NOT_FOUND = 'Payment record not found';
    public const INVALID_DISCOUNT_CALCULATION = 'Invalid discount calculation';

    // Error Messages - User
    public const USER_NOT_AUTHENTICATED = 'User not authenticated';

    // Error Messages - Stripe
    public const STRIPE_NOT_INITIALIZED = 'Stripe not initialized';
    public const STRIPE_CUSTOMER_CREATION_FAILED = 'Failed to create Stripe customer';

    // Validation Messages
    public const VALIDATION_FAILED = 'Validation failed';
    public const FIELD_REQUIRED = 'This field is required';
    public const FIELD_MUST_BE_STRING = 'This field must be a string';
    public const FIELD_MUST_EXIST = 'The selected field is invalid';
}
