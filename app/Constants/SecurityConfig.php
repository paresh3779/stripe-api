<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Security configuration constants
 */
class SecurityConfig
{
    // Rate limiting
    public const RATE_LIMIT_DEFAULT = 60; // requests per minute
    public const RATE_LIMIT_STRICT = 10; // for payment operations
    public const RATE_LIMIT_WEBHOOK = 100; // for webhooks

    // Token expiry
    public const TOKEN_EXPIRY_MINUTES = 60;
    public const REFRESH_TOKEN_EXPIRY_DAYS = 7;

    // Input validation
    public const MAX_STRING_LENGTH = 255;
    public const MAX_DESCRIPTION_LENGTH = 1000;
    public const MIN_PROMO_CODE_LENGTH = 3;
    public const MAX_PROMO_CODE_LENGTH = 50;

    // Amount limits (in cents)
    public const MIN_AMOUNT = 50; // $0.50 minimum for Stripe
    public const MAX_AMOUNT = 99999999; // $999,999.99 maximum

    // Retry configuration
    public const MAX_RETRY_ATTEMPTS = 3;
    public const RETRY_DELAY_SECONDS = 1;

    // Cache TTL (seconds)
    public const CACHE_TTL_PRODUCTS = 300; // 5 minutes
    public const CACHE_TTL_COUPONS = 60; // 1 minute
    public const CACHE_TTL_CUSTOMER = 3600; // 1 hour

    // Allowed payment methods
    public const ALLOWED_PAYMENT_METHODS = ['card'];

    // Allowed currencies
    public const ALLOWED_CURRENCIES = ['usd', 'eur', 'gbp'];

    // IP whitelist for webhooks (optional - empty means allow all)
    public const WEBHOOK_IP_WHITELIST = [];
}
