<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CouponsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('coupons')->insert([
            [
                'id' => Str::uuid(),
                'name' => 'Welcome Discount',
                'description' => '10% off your first purchase',
                'stripe_coupon_id' => 'co_' . Str::random(10),
                'discount_type' => 'percentage',
                'discount_value' => 10,
                'currency' => 'usd',
                'duration' => 'forever',
                'max_redemptions' => null,
                'times_redeemed' => 0,
                'valid_from' => now(),
                'valid_until' => null,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Subscription Saver',
                'description' => '$20 off monthly subscriptions',
                'stripe_coupon_id' => 'co_' . Str::random(10),
                'discount_type' => 'fixed',
                'discount_value' => 2000, // $20 in cents
                'currency' => 'usd',
                'duration' => 'once',
                'max_redemptions' => 100,
                'times_redeemed' => 0,
                'valid_from' => now(),
                'valid_until' => now()->addMonths(3),
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'name' => 'One-Time Special',
                'description' => '15% off one-time purchases',
                'stripe_coupon_id' => 'co_' . Str::random(10),
                'discount_type' => 'percentage',
                'discount_value' => 15,
                'currency' => 'usd',
                'duration' => 'repeating',
                'max_redemptions' => 50,
                'times_redeemed' => 0,
                'valid_from' => now(),
                'valid_until' => now()->addMonths(6),
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
