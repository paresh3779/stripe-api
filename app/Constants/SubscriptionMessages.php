<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Subscription-related messages and validation constants
 */
class SubscriptionMessages
{
    // Success Messages
    public const CHECKOUT_SESSION_CREATED = 'Subscription checkout session created successfully';
    public const SUBSCRIPTION_CREATED = 'Subscription created successfully';
    public const SUBSCRIPTION_UPDATED = 'Subscription updated successfully';
    public const SUBSCRIPTION_CANCELLED = 'Subscription cancelled successfully';
    public const TRIAL_STARTED = 'Trial period started successfully';
    public const PROMO_CODE_APPLIED = 'Promo code applied to subscription successfully';
    public const COUPON_APPLIED = 'Coupon applied to subscription successfully';

    // Error Messages - Products
    public const PRODUCT_NOT_FOUND = 'Subscription product not found';
    public const PRODUCT_NOT_AVAILABLE = 'Subscription product is not available';
    public const PRODUCT_NOT_RECURRING = 'This product does not support recurring billing';

    // Error Messages - Prices
    public const PRICE_NOT_FOUND = 'Subscription price not found';
    public const PRICE_NOT_ACTIVE = 'This subscription price is not active';
    public const PRICE_NOT_RECURRING = 'This price is not for recurring subscription';
    public const INVALID_BILLING_INTERVAL = 'Invalid billing interval specified';

    // Error Messages - Trial
    public const TRIAL_NOT_AVAILABLE = 'Trial period is not available for this plan';
    public const TRIAL_ALREADY_USED = 'You have already used your trial period';
    public const INVALID_TRIAL_DAYS = 'Invalid trial period specified';

    // Error Messages - Promo Codes
    public const PROMO_CODE_INVALID = 'Invalid promo code for subscription';
    public const PROMO_CODE_NOT_ACTIVE = 'Promo code is not active';
    public const PROMO_CODE_EXPIRED = 'Promo code has expired';
    public const PROMO_CODE_NOT_APPLICABLE = 'Promo code is not applicable to subscriptions';

    // Error Messages - Coupons
    public const COUPON_INVALID = 'Invalid coupon for subscription';
    public const COUPON_NOT_ACTIVE = 'Coupon is not active';
    public const COUPON_EXPIRED = 'Coupon has expired';
    public const COUPON_NOT_APPLICABLE = 'Coupon is not applicable to subscriptions';

    // Error Messages - Subscription
    public const SUBSCRIPTION_NOT_FOUND = 'Subscription not found';
    public const SUBSCRIPTION_ALREADY_ACTIVE = 'You already have an active subscription';
    public const SUBSCRIPTION_CREATION_FAILED = 'Failed to create subscription';
    public const CHECKOUT_SESSION_FAILED = 'Failed to create checkout session';

    // Error Messages - Customer
    public const CUSTOMER_NOT_FOUND = 'Customer not found';
    public const CUSTOMER_CREATION_FAILED = 'Failed to create customer';

    // Error Messages - User
    public const USER_NOT_AUTHENTICATED = 'User not authenticated';

    // Validation Messages
    public const PRICE_ID_REQUIRED = 'Price ID is required';
    public const BILLING_INTERVAL_REQUIRED = 'Billing interval is required';
    public const VALID_BILLING_INTERVALS = 'Billing interval must be either monthly or yearly';
}
