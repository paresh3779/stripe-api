<?php

namespace App\Constants;

class DiscountType
{
    public const PERCENTAGE = 'percentage';
    public const FIXED_AMOUNT = 'fixed_amount';

    /**
     * Get all discount types
     *
     * @return array
     */
    public static function all(): array
    {
        return [
            self::PERCENTAGE,
            self::FIXED_AMOUNT,
        ];
    }

    /**
     * Check if discount type is valid
     *
     * @param string $type
     * @return bool
     */
    public static function isValid(string $type): bool
    {
        return in_array($type, self::all());
    }
}
