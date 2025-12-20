<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Product extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'image',
        'stripe_product_id',
        'type',
        'active',
        'is_archived',
        'archived_date',
    ];

    protected $casts = [
        'active' => 'boolean',
        'is_archived' => 'boolean',
        'archived_date' => 'datetime',
    ];

    public function prices()
    {
        return $this->hasMany(Price::class);
    }
}
