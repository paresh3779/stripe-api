<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'subscription_id',
        'stripe_invoice_id',
        'stripe_customer_id',
        'number',
        'status',
        'amount_due',
        'amount_paid',
        'amount_remaining',
        'subtotal',
        'total',
        'tax',
        'currency',
        'description',
        'hosted_invoice_url',
        'invoice_pdf',
        'due_date',
        'paid_at',
        'period_start',
        'period_end',
        'line_items',
        'metadata',
    ];

    protected $casts = [
        'amount_due' => 'integer',
        'amount_paid' => 'integer',
        'amount_remaining' => 'integer',
        'subtotal' => 'integer',
        'total' => 'integer',
        'tax' => 'integer',
        'due_date' => 'datetime',
        'paid_at' => 'datetime',
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'line_items' => 'array',
        'metadata' => 'array',
    ];

    /**
     * Check if invoice is paid
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Check if invoice is open (awaiting payment)
     */
    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /**
     * Check if invoice is overdue
     */
    public function isOverdue(): bool
    {
        if (!$this->due_date || $this->isPaid()) {
            return false;
        }

        return now()->isAfter($this->due_date);
    }

    /**
     * Get the user that owns the invoice
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the subscription associated with the invoice
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Scope for paid invoices
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    /**
     * Scope for user invoices
     */
    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for subscription invoices
     */
    public function scopeForSubscription($query, string $subscriptionId)
    {
        return $query->where('subscription_id', $subscriptionId);
    }
}
