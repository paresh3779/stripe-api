<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PromoCodesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $coupons = DB::table('coupons')->pluck('id', 'name');

        DB::table('promo_codes')->insert([
            [
                'id' => Str::uuid(),
                'coupon_id' => $coupons['Welcome Discount'],
                'code' => 'WELCOME10',
                'description' => 'Welcome discount code for 10% off',
                'stripe_promotion_code_id' => 'promo_' . Str::random(10),
                'max_redemptions' => 1000,
                'times_redeemed' => 0,
                'active' => true,
                'valid_from' => now(),
                'valid_until' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'coupon_id' => $coupons['Subscription Saver'],
                'code' => 'SAVE20',
                'description' => 'Save $20 on monthly subscriptions',
                'stripe_promotion_code_id' => 'promo_' . Str::random(10),
                'max_redemptions' => 100,
                'times_redeemed' => 0,
                'active' => true,
                'valid_from' => now(),
                'valid_until' => now()->addMonths(3),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'coupon_id' => $coupons['One-Time Special'],
                'code' => 'SPECIAL15',
                'description' => 'Special 15% off on one-time purchases',
                'stripe_promotion_code_id' => 'promo_' . Str::random(10),
                'max_redemptions' => 50,
                'times_redeemed' => 0,
                'active' => true,
                'valid_from' => now(),
                'valid_until' => now()->addMonths(6),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
