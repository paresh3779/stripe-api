<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class StripeWebhookEvent extends Model
{
    use HasUuids;

    protected $fillable = [
        'stripe_event_id',
        'event_type',
        'status',
        'payload',
        'error_message',
        'retry_count',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];

    // Status constants
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    /**
     * Check if this event has already been processed
     */
    public static function isProcessed(string $stripeEventId): bool
    {
        return self::where('stripe_event_id', $stripeEventId)
            ->whereIn('status', [self::STATUS_PROCESSED, self::STATUS_PROCESSING])
            ->exists();
    }

    /**
     * Record a new webhook event for processing
     */
    public static function recordEvent(string $stripeEventId, string $eventType, array $payload = []): ?self
    {
        // Check if already processed (idempotency check)
        if (self::isProcessed($stripeEventId)) {
            \Log::info('Webhook event already processed, skipping', [
                'stripe_event_id' => $stripeEventId,
                'event_type' => $eventType,
            ]);
            return null;
        }

        return self::create([
            'stripe_event_id' => $stripeEventId,
            'event_type' => $eventType,
            'status' => self::STATUS_PROCESSING,
            'payload' => $payload,
        ]);
    }

    /**
     * Mark event as successfully processed
     */
    public function markAsProcessed(): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSED,
            'processed_at' => now(),
        ]);
    }

    /**
     * Mark event as failed
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'retry_count' => $this->retry_count + 1,
        ]);
    }

    /**
     * Mark event as skipped (duplicate or not applicable)
     */
    public function markAsSkipped(string $reason = null): void
    {
        $this->update([
            'status' => self::STATUS_SKIPPED,
            'error_message' => $reason,
            'processed_at' => now(),
        ]);
    }

    /**
     * Scope for failed events that can be retried
     */
    public function scopeRetryable($query, int $maxRetries = 3)
    {
        return $query->where('status', self::STATUS_FAILED)
            ->where('retry_count', '<', $maxRetries);
    }

    /**
     * Scope for events by type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('event_type', $type);
    }
}
