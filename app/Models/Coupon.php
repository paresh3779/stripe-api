<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Coupon extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'description',
        'stripe_coupon_id',
        'discount_type',
        'discount_value',
        'currency',
        'duration',
        'max_redemptions',
        'times_redeemed',
        'valid_from',
        'valid_until',
        'active',
    ];

    protected $casts = [
        'discount_value' => 'integer',
        'max_redemptions' => 'integer',
        'times_redeemed' => 'integer',
        'active' => 'boolean',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
    ];

    public function promoCodes()
    {
        return $this->hasMany(PromoCode::class);
    }
}
