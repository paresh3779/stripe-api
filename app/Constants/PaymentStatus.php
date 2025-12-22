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
