<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'product_id',
        'price_id',
        'stripe_subscription_id',
        'stripe_customer_id',
        'status',
        'billing_interval',
        'interval_count',
        'amount',
        'currency',
        'trial_start',
        'trial_end',
        'current_period_start',
        'current_period_end',
        'canceled_at',
        'ended_at',
        'cancel_at_period_end',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'integer',
        'interval_count' => 'integer',
        'cancel_at_period_end' => 'boolean',
        'trial_start' => 'datetime',
        'trial_end' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'canceled_at' => 'datetime',
        'ended_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Check if subscription is active
     */
    public function isActive(): bool
    {
        return in_array($this->status, ['active', 'trialing']);
    }

    /**
     * Check if subscription can be cancelled with refund (within 7 days)
     */
    public function canRefund(): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        $createdAt = $this->created_at;
        $sevenDaysAgo = now()->subDays(7);

        return $createdAt->isAfter($sevenDaysAgo);
    }

    /**
     * Check if subscription is in trial period
     */
    public function isTrialing(): bool
    {
        return $this->status === 'trialing';
    }

    /**
     * Check if subscription is past due
     */
    public function isPastDue(): bool
    {
        return $this->status === 'past_due';
    }

    /**
     * Check if subscription is cancelled
     */
    public function isCancelled(): bool
    {
        return $this->status === 'canceled' || $this->canceled_at !== null;
    }

    /**
     * Get days until subscription expires
     */
    public function daysUntilExpiry(): ?int
    {
        if (!$this->current_period_end) {
            return null;
        }

        return now()->diffInDays($this->current_period_end, false);
    }

    /**
     * Get the user that owns the subscription
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the product associated with the subscription
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the price associated with the subscription
     */
    public function price(): BelongsTo
    {
        return $this->belongsTo(Price::class);
    }

    /**
     * Get all invoices for this subscription
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Scope for active subscriptions
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['active', 'trialing']);
    }

    /**
     * Scope for user subscriptions
     */
    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }
}
