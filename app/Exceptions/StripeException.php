<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\CardException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Exception\AuthenticationException;
use Stripe\Exception\RateLimitException;

/**
 * Custom exception handler for Stripe-related errors
 * Provides user-friendly error messages while logging technical details
 */
class StripeException extends Exception
{
    protected string $stripeErrorCode;
    protected string $stripeErrorType;

    /**
     * Create a new StripeException from a Stripe API exception
     *
     * @param ApiErrorException $e
     * @return static
     */
    public static function fromStripeException(ApiErrorException $e): static
    {
        $message = static::getUserFriendlyMessage($e);
        $exception = new static($message, $e->getCode(), $e);
        
        if (method_exists($e, 'getStripeCode')) {
            $exception->stripeErrorCode = $e->getStripeCode() ?? 'unknown';
        }
        
        $exception->stripeErrorType = get_class($e);
        
        return $exception;
    }

    /**
     * Get a user-friendly error message from a Stripe exception
     *
     * @param ApiErrorException $e
     * @return string
     */
    protected static function getUserFriendlyMessage(ApiErrorException $e): string
    {
        if ($e instanceof CardException) {
            return match ($e->getStripeCode()) {
                'card_declined' => 'Your card was declined. Please try a different payment method.',
                'expired_card' => 'Your card has expired. Please use a different card.',
                'incorrect_cvc' => 'The security code (CVC) is incorrect.',
                'processing_error' => 'An error occurred while processing your card. Please try again.',
                'incorrect_number' => 'The card number is incorrect.',
                'insufficient_funds' => 'Your card has insufficient funds.',
                default => 'There was an issue with your card. Please try a different payment method.',
            };
        }

        if ($e instanceof InvalidRequestException) {
            return 'Invalid request. Please check your information and try again.';
        }

        if ($e instanceof AuthenticationException) {
            return 'Payment service configuration error. Please contact support.';
        }

        if ($e instanceof RateLimitException) {
            return 'Too many requests. Please wait a moment and try again.';
        }

        return 'A payment error occurred. Please try again later.';
    }

    /**
     * Get the Stripe error code
     *
     * @return string
     */
    public function getStripeErrorCode(): string
    {
        return $this->stripeErrorCode ?? 'unknown';
    }

    /**
     * Get the Stripe error type
     *
     * @return string
     */
    public function getStripeErrorType(): string
    {
        return $this->stripeErrorType ?? 'unknown';
    }

    /**
     * Convert exception to array for JSON response
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'success' => false,
            'message' => $this->getMessage(),
            'error_code' => $this->getStripeErrorCode(),
        ];
    }
}
