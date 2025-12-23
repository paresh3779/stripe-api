<?php

declare(strict_types=1);

namespace App\Traits;

/**
 * Trait for price formatting
 * Provides reusable price formatting logic
 */
trait PriceFormatterTrait
{
    /**
     * Format amount from cents to dollars
     *
     * @param int $cents
     * @return float
     */
    protected function centsToDecimal(int $cents): float
    {
        return $cents / 100;
    }

    /**
     * Format amount from dollars to cents
     *
     * @param float $dollars
     * @return int
     */
    protected function decimalToCents(float $dollars): int
    {
        return (int) round($dollars * 100);
    }

    /**
     * Format price for display
     *
     * @param int $cents
     * @param string $currency
     * @return string
     */
    protected function formatPrice(int $cents, string $currency = 'usd'): string
    {
        $formatter = new \NumberFormatter('en_US', \NumberFormatter::CURRENCY);
        return $formatter->formatCurrency($this->centsToDecimal($cents), strtoupper($currency));
    }

    /**
     * Get interval label
     *
     * @param string|null $interval
     * @return string
     */
    protected function getIntervalLabel(?string $interval): string
    {
        return match ($interval) {
            'month' => '/month',
            'year' => '/year',
            'week' => '/week',
            'day' => '/day',
            default => '',
        };
    }
}
