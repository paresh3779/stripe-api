<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class PromoCode extends Model
{
    use HasUuids;

    protected $fillable = [
        'coupon_id',
        'code',
        'description',
        'stripe_promotion_code_id',
        'max_redemptions',
        'times_redeemed',
        'active',
        'valid_from',
        'valid_until',
    ];

    protected $casts = [
        'max_redemptions' => 'integer',
        'times_redeemed' => 'integer',
        'active' => 'boolean',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
    ];

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }
}
