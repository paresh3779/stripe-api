<?php

namespace App\Constants;

class PaymentStatus
{
    public const PENDING = 'pending';
    public const SUCCEEDED = 'succeeded';
    public const FAILED = 'failed';
    public const REFUNDED = 'refunded';
    public const PARTIALLY_REFUNDED = 'partially_refunded';
    public const DISPUTED = 'disputed';
    public const PAID = 'paid';
    public const CANCELLED = 'cancelled';
    
    // Additional statuses for production-ready handling
    public const PROCESSING = 'processing';
    public const REQUIRES_ACTION = 'requires_action';
    public const REQUIRES_PAYMENT_METHOD = 'requires_payment_method';
    public const UNDER_REVIEW = 'under_review';
    public const EXPIRED = 'expired';

    /**
     * Get all payment statuses
     *
     * @return array
     */
    public static function all(): array
    {
        return [
            self::PENDING,
            self::SUCCEEDED,
            self::FAILED,
            self::REFUNDED,
            self::PARTIALLY_REFUNDED,
            self::DISPUTED,
            self::PAID,
            self::CANCELLED,
            self::PROCESSING,
            self::REQUIRES_ACTION,
            self::REQUIRES_PAYMENT_METHOD,
            self::UNDER_REVIEW,
            self::EXPIRED,
        ];
    }

    /**
     * Get statuses that indicate payment is in progress
     */
    public static function inProgress(): array
    {
        return [
            self::PENDING,
            self::PROCESSING,
            self::REQUIRES_ACTION,
            self::REQUIRES_PAYMENT_METHOD,
        ];
    }

    /**
     * Get statuses that indicate payment completed successfully
     */
    public static function successful(): array
    {
        return [
            self::SUCCEEDED,
            self::PAID,
        ];
    }

    /**
     * Get statuses that indicate payment did not complete
     */
    public static function unsuccessful(): array
    {
        return [
            self::FAILED,
            self::CANCELLED,
            self::EXPIRED,
        ];
    }

    /**
     * Check if status is valid
     *
     * @param string $status
     * @return bool
     */
    public static function isValid(string $status): bool
    {
        return in_array($status, self::all());
    }
}
