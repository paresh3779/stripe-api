<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StripePaymentMethod extends Model
{
    use HasUuids;

    protected $fillable = [
        'customer_id',
        'stripe_payment_method_id',
        'type',
        'card_brand',
        'last4',
        'exp_month',
        'exp_year',
        'is_default',
    ];

    protected $casts = [
        'exp_month' => 'integer',
        'exp_year' => 'integer',
        'is_default' => 'boolean',
    ];

    /**
     * Get the stripe customer that owns this payment method
     *
     * @return BelongsTo
     */
    public function stripeCustomer(): BelongsTo
    {
        return $this->belongsTo(StripeCustomer::class, 'customer_id');
    }
}
