<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PricesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = DB::table('products')->pluck('id', 'slug');

        DB::table('prices')->insert([
            [
                'id' => Str::uuid(),
                'product_id' => $products['advanced-analytics-dashboard'],
                'stripe_price_id' => 'price_' . Str::random(10),
                'description' => 'Monthly subscription',
                'amount' => 2999, // $29.99 in cents
                'currency' => 'usd',
                'type' => 'recurring',
                'interval' => 'month',
                'interval_count' => 1,
                'trial_days' => 7,
                'features' => json_encode(['Real-time analytics', 'Custom reports']),
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'product_id' => $products['advanced-analytics-dashboard'],
                'stripe_price_id' => 'price_' . Str::random(10),
                'description' => 'Yearly subscription',
                'amount' => 29999, // $299.99 in cents
                'currency' => 'usd',
                'type' => 'recurring',
                'interval' => 'year',
                'interval_count' => 1,
                'trial_days' => 14,
                'features' => json_encode(['Real-time analytics', 'Custom reports', 'Priority support']),
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'product_id' => $products['online-learning-platform'],
                'stripe_price_id' => 'price_' . Str::random(10),
                'description' => 'One-time purchase',
                'amount' => 9999, // $99.99 in cents
                'currency' => 'usd',
                'type' => 'one_time',
                'interval' => null,
                'interval_count' => 1,
                'trial_days' => null,
                'features' => json_encode(['Lifetime access', 'Certificate of completion']),
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'product_id' => $products['api-gateway-service'],
                'stripe_price_id' => 'price_' . Str::random(10),
                'description' => 'Per API call',
                'amount' => 10, // $0.10 in cents
                'currency' => 'usd',
                'type' => 'recurring',
                'interval' => 'month',
                'interval_count' => 1,
                'trial_days' => null,
                'features' => json_encode(['Unlimited API calls', 'Rate limiting']),
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'product_id' => $products['digital-marketing-toolkit'],
                'stripe_price_id' => 'price_' . Str::random(10),
                'description' => 'Monthly subscription',
                'amount' => 4999, // $49.99 in cents
                'currency' => 'usd',
                'type' => 'recurring',
                'interval' => 'month',
                'interval_count' => 1,
                'trial_days' => 7,
                'features' => json_encode(['SEO tools', 'Social media scheduler']),
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'product_id' => $products['digital-marketing-toolkit'],
                'stripe_price_id' => 'price_' . Str::random(10),
                'description' => 'Yearly subscription',
                'amount' => 49999, // $499.99 in cents
                'currency' => 'usd',
                'type' => 'recurring',
                'interval' => 'year',
                'interval_count' => 1,
                'trial_days' => 14,
                'features' => json_encode(['SEO tools', 'Social media scheduler', 'Advanced analytics']),
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'product_id' => $products['e-commerce-platform'],
                'stripe_price_id' => 'price_' . Str::random(10),
                'description' => 'One-time purchase',
                'amount' => 19999, // $199.99 in cents
                'currency' => 'usd',
                'type' => 'one_time',
                'interval' => null,
                'interval_count' => 1,
                'trial_days' => null,
                'features' => json_encode(['Full e-commerce features', 'Payment integration']),
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
