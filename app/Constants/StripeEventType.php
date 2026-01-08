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
    public const SUBSCRIPTION_CREATED = 'customer.subscription.created';
    public const SUBSCRIPTION_DELETED = 'customer.subscription.deleted';
    public const PROMOTION_CODE_CREATED = 'promotion_code.created';
    public const PROMOTION_CODE_UPDATED = 'promotion_code.updated';
    public const PROMOTION_CODE_EXPIRED = 'promotion_code.expired';
    public const INVOICE_CREATED = 'invoice.created';
    public const INVOICE_FINALIZED = 'invoice.finalized';
    public const INVOICE_PAID = 'invoice.paid';
    public const INVOICE_PAYMENT_FAILED = 'invoice.payment_failed';
    public const INVOICE_UPCOMING = 'invoice.upcoming';
    public const INVOICE_VOIDED = 'invoice.voided';
    public const INVOICE_MARKED_UNCOLLECTIBLE = 'invoice.marked_uncollectible';

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
            self::SUBSCRIPTION_CREATED,
            self::SUBSCRIPTION_DELETED,
            self::PROMOTION_CODE_CREATED,
            self::PROMOTION_CODE_UPDATED,
            self::PROMOTION_CODE_EXPIRED,
            self::INVOICE_CREATED,
            self::INVOICE_FINALIZED,
            self::INVOICE_PAID,
            self::INVOICE_PAYMENT_FAILED,
            self::INVOICE_UPCOMING,
            self::INVOICE_VOIDED,
            self::INVOICE_MARKED_UNCOLLECTIBLE,
        ];
    }
}
