<?php

namespace App\Constants;

class StripeEventType
{
    public const CHECKOUT_SESSION_COMPLETED = 'checkout.session.completed';
    public const PAYMENT_INTENT_SUCCEEDED = 'payment_intent.succeeded';
    public const PAYMENT_INTENT_FAILED = 'payment_intent.payment_failed';
    public const CHARGE_REFUNDED = 'charge.refunded';
    public const CHARGE_DISPUTE_CREATED = 'charge.dispute.created';
    public const CHARGE_DISPUTE_CLOSED = 'charge.dispute.closed';
    public const SUBSCRIPTION_TRIAL_WILL_END = 'customer.subscription.trial_will_end';
    public const SUBSCRIPTION_UPDATED = 'customer.subscription.updated';
    public const PROMOTION_CODE_CREATED = 'promotion_code.created';
    public const PROMOTION_CODE_UPDATED = 'promotion_code.updated';
    public const PROMOTION_CODE_EXPIRED = 'promotion_code.expired';
    public const INVOICE_PAID = 'invoice.paid';

    /**
     * Get all event types
     *
     * @return array
     */
    public static function all(): array
    {
        return [
            self::CHECKOUT_SESSION_COMPLETED,
            self::PAYMENT_INTENT_SUCCEEDED,
            self::PAYMENT_INTENT_FAILED,
            self::CHARGE_REFUNDED,
            self::CHARGE_DISPUTE_CREATED,
            self::CHARGE_DISPUTE_CLOSED,
            self::SUBSCRIPTION_TRIAL_WILL_END,
            self::SUBSCRIPTION_UPDATED,
            self::PROMOTION_CODE_CREATED,
            self::PROMOTION_CODE_UPDATED,
            self::PROMOTION_CODE_EXPIRED,
            self::INVOICE_PAID,
        ];
    }
}
