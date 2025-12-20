<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Price extends Model
{
    use HasUuids;

    protected $fillable = [
        'product_id',
        'stripe_price_id',
        'description',
        'amount',
        'currency',
        'type',
        'interval',
        'interval_count',
        'trial_days',
        'features',
        'active',
    ];

    protected $casts = [
        'amount' => 'integer',
        'interval_count' => 'integer',
        'trial_days' => 'integer',
        'features' => 'array',
        'active' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
