<?php

namespace App\Constants;

class StripeEventType
{
    // Checkout Session Events
    public const CHECKOUT_SESSION_COMPLETED = 'checkout.session.completed';
    public const CHECKOUT_SESSION_EXPIRED = 'checkout.session.expired';
    public const CHECKOUT_SESSION_ASYNC_PAYMENT_SUCCEEDED = 'checkout.session.async_payment_succeeded';
    public const CHECKOUT_SESSION_ASYNC_PAYMENT_FAILED = 'checkout.session.async_payment_failed';

    // Payment Intent Events
    public const PAYMENT_INTENT_SUCCEEDED = 'payment_intent.succeeded';
    public const PAYMENT_INTENT_FAILED = 'payment_intent.payment_failed';
    public const PAYMENT_INTENT_REQUIRES_ACTION = 'payment_intent.requires_action';
    public const PAYMENT_INTENT_PROCESSING = 'payment_intent.processing';
    public const PAYMENT_INTENT_CANCELED = 'payment_intent.canceled';
    public const PAYMENT_INTENT_CREATED = 'payment_intent.created';

    // Charge Events
    public const CHARGE_REFUNDED = 'charge.refunded';
    public const CHARGE_DISPUTE_CREATED = 'charge.dispute.created';
    public const CHARGE_DISPUTE_CLOSED = 'charge.dispute.closed';
    public const CHARGE_FAILED = 'charge.failed';
    public const CHARGE_SUCCEEDED = 'charge.succeeded';

    // Radar / Review Events (Fraud Detection)
    public const REVIEW_OPENED = 'review.opened';
    public const REVIEW_CLOSED = 'review.closed';

    // Refund Events
    public const REFUND_CREATED = 'refund.created';
    public const REFUND_UPDATED = 'refund.updated';
    public const REFUND_FAILED = 'refund.failed';
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
            // Checkout Session
            self::CHECKOUT_SESSION_COMPLETED,
            self::CHECKOUT_SESSION_EXPIRED,
            self::CHECKOUT_SESSION_ASYNC_PAYMENT_SUCCEEDED,
            self::CHECKOUT_SESSION_ASYNC_PAYMENT_FAILED,
            // Payment Intent
            self::PAYMENT_INTENT_SUCCEEDED,
            self::PAYMENT_INTENT_FAILED,
            self::PAYMENT_INTENT_REQUIRES_ACTION,
            self::PAYMENT_INTENT_PROCESSING,
            self::PAYMENT_INTENT_CANCELED,
            self::PAYMENT_INTENT_CREATED,
            // Charge
            self::CHARGE_REFUNDED,
            self::CHARGE_DISPUTE_CREATED,
            self::CHARGE_DISPUTE_CLOSED,
            self::CHARGE_FAILED,
            self::CHARGE_SUCCEEDED,
            // Review/Radar
            self::REVIEW_OPENED,
            self::REVIEW_CLOSED,
            // Refund
            self::REFUND_CREATED,
            self::REFUND_UPDATED,
            self::REFUND_FAILED,
            // Subscription
            self::SUBSCRIPTION_TRIAL_WILL_END,
            self::SUBSCRIPTION_UPDATED,
            self::SUBSCRIPTION_CREATED,
            self::SUBSCRIPTION_DELETED,
            // Promotion Code
            self::PROMOTION_CODE_CREATED,
            self::PROMOTION_CODE_UPDATED,
            self::PROMOTION_CODE_EXPIRED,
            // Invoice
            self::INVOICE_CREATED,
            self::INVOICE_FINALIZED,
            self::INVOICE_PAID,
            self::INVOICE_PAYMENT_FAILED,
            self::INVOICE_UPCOMING,
            self::INVOICE_VOIDED,
            self::INVOICE_MARKED_UNCOLLECTIBLE,
        ];
    }

    /**
     * Get critical payment events that require immediate handling
     */
    public static function criticalPaymentEvents(): array
    {
        return [
            self::PAYMENT_INTENT_SUCCEEDED,
            self::PAYMENT_INTENT_FAILED,
            self::PAYMENT_INTENT_REQUIRES_ACTION,
            self::CHECKOUT_SESSION_COMPLETED,
            self::CHECKOUT_SESSION_EXPIRED,
            self::CHARGE_FAILED,
        ];
    }
}
