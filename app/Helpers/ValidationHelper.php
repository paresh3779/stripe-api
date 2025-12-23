<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Constants\SecurityConfig;

/**
 * Helper class for input validation
 */
class ValidationHelper
{
    /**
     * Validate and sanitize a UUID
     *
     * @param string $uuid
     * @return bool
     */
    public static function isValidUuid(string $uuid): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid) === 1;
    }

    /**
     * Validate a Stripe payment method ID
     *
     * @param string $paymentMethodId
     * @return bool
     */
    public static function isValidPaymentMethodId(string $paymentMethodId): bool
    {
        return preg_match('/^pm_[a-zA-Z0-9]+$/', $paymentMethodId) === 1;
    }

    /**
     * Validate a Stripe customer ID
     *
     * @param string $customerId
     * @return bool
     */
    public static function isValidCustomerId(string $customerId): bool
    {
        return preg_match('/^cus_[a-zA-Z0-9]+$/', $customerId) === 1;
    }

    /**
     * Validate a Stripe subscription ID
     *
     * @param string $subscriptionId
     * @return bool
     */
    public static function isValidSubscriptionId(string $subscriptionId): bool
    {
        return preg_match('/^sub_[a-zA-Z0-9]+$/', $subscriptionId) === 1;
    }

    /**
     * Validate an amount is within allowed range
     *
     * @param int $amount Amount in cents
     * @return bool
     */
    public static function isValidAmount(int $amount): bool
    {
        return $amount >= SecurityConfig::MIN_AMOUNT && $amount <= SecurityConfig::MAX_AMOUNT;
    }

    /**
     * Validate a promo code format
     *
     * @param string $code
     * @return bool
     */
    public static function isValidPromoCode(string $code): bool
    {
        $length = strlen($code);
        return $length >= SecurityConfig::MIN_PROMO_CODE_LENGTH
            && $length <= SecurityConfig::MAX_PROMO_CODE_LENGTH
            && preg_match('/^[A-Z0-9]+$/', $code) === 1;
    }

    /**
     * Validate an email address
     *
     * @param string $email
     * @return bool
     */
    public static function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Sanitize a string for safe storage/display
     *
     * @param string $input
     * @param int $maxLength
     * @return string
     */
    public static function sanitizeString(string $input, int $maxLength = 255): string
    {
        $input = trim($input);
        $input = strip_tags($input);
        $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return mb_substr($input, 0, $maxLength);
    }

    /**
     * Validate currency code
     *
     * @param string $currency
     * @return bool
     */
    public static function isValidCurrency(string $currency): bool
    {
        return in_array(strtolower($currency), SecurityConfig::ALLOWED_CURRENCIES, true);
    }
}
