<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Stripe configuration constants
 */
class StripeConfig
{
    // Payment modes
    public const MODE_PAYMENT = 'payment';
    public const MODE_SUBSCRIPTION = 'subscription';
    public const MODE_SETUP = 'setup';

    // Billing intervals
    public const INTERVAL_DAY = 'day';
    public const INTERVAL_WEEK = 'week';
    public const INTERVAL_MONTH = 'month';
    public const INTERVAL_YEAR = 'year';

    // Price types
    public const PRICE_TYPE_ONE_TIME = 'one_time';
    public const PRICE_TYPE_RECURRING = 'recurring';

    // Subscription statuses
    public const STATUS_ACTIVE = 'active';
    public const STATUS_TRIALING = 'trialing';
    public const STATUS_INCOMPLETE = 'incomplete';
    public const STATUS_INCOMPLETE_EXPIRED = 'incomplete_expired';
    public const STATUS_PAST_DUE = 'past_due';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_UNPAID = 'unpaid';

    // Payment intent statuses
    public const PI_STATUS_SUCCEEDED = 'succeeded';
    public const PI_STATUS_PROCESSING = 'processing';
    public const PI_STATUS_REQUIRES_PAYMENT = 'requires_payment_method';
    public const PI_STATUS_REQUIRES_CONFIRMATION = 'requires_confirmation';
    public const PI_STATUS_REQUIRES_ACTION = 'requires_action';
    public const PI_STATUS_CANCELED = 'canceled';

    // Checkout session URLs
    public const SUCCESS_URL_PATH = '/success';
    public const CANCEL_URL_PATH = '/cancel';

    // API version
    public const API_VERSION = '2023-10-16';

    /**
     * Get all valid billing intervals
     *
     * @return array
     */
    public static function getValidIntervals(): array
    {
        return [
            self::INTERVAL_DAY,
            self::INTERVAL_WEEK,
            self::INTERVAL_MONTH,
            self::INTERVAL_YEAR,
        ];
    }

    /**
     * Get all valid subscription statuses
     *
     * @return array
     */
    public static function getValidSubscriptionStatuses(): array
    {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_TRIALING,
            self::STATUS_INCOMPLETE,
            self::STATUS_INCOMPLETE_EXPIRED,
            self::STATUS_PAST_DUE,
            self::STATUS_CANCELED,
            self::STATUS_UNPAID,
        ];
    }

    /**
     * Check if subscription status is active
     *
     * @param string $status
     * @return bool
     */
    public static function isActiveStatus(string $status): bool
    {
        return in_array($status, [self::STATUS_ACTIVE, self::STATUS_TRIALING], true);
    }
}
