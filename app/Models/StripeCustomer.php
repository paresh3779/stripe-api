<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StripeCustomer extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'stripe_customer_id',
        'email',
    ];

    /**
     * Get the user that owns the Stripe customer
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
