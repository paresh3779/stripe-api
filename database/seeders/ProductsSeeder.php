<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('products')->insert([
            [
                'id' => Str::uuid(),
                'name' => 'Advanced Analytics Dashboard',
                'slug' => Str::slug('Advanced Analytics Dashboard'),
                'description' => 'Comprehensive analytics and reporting tool for businesses to track performance and insights.',
                'image' => 'https://example.com/analytics-dashboard.jpg',
                'stripe_product_id' => 'prod_' . Str::random(10),
                'type' => 'subscription',
                'active' => true,
                'is_archived' => false,
                'archived_date' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Online Learning Platform',
                'slug' => Str::slug('Online Learning Platform'),
                'description' => 'Access to premium courses, certifications, and educational resources for professional development.',
                'image' => 'https://example.com/learning-platform.jpg',
                'stripe_product_id' => 'prod_' . Str::random(10),
                'type' => 'one_time',
                'active' => true,
                'is_archived' => false,
                'archived_date' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'name' => 'API Gateway Service',
                'slug' => Str::slug('API Gateway Service'),
                'description' => 'Scalable API management, monitoring, and security for enterprise applications.',
                'image' => 'https://example.com/api-gateway.jpg',
                'stripe_product_id' => 'prod_' . Str::random(10),
                'type' => 'usage_based',
                'active' => true,
                'is_archived' => false,
                'archived_date' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Digital Marketing Toolkit',
                'slug' => Str::slug('Digital Marketing Toolkit'),
                'description' => 'Complete toolkit for SEO, social media management, and email marketing campaigns.',
                'image' => 'https://example.com/marketing-toolkit.jpg',
                'stripe_product_id' => 'prod_' . Str::random(10),
                'type' => 'subscription',
                'active' => true,
                'is_archived' => false,
                'archived_date' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'name' => 'E-commerce Platform',
                'slug' => Str::slug('E-commerce Platform'),
                'description' => 'Full-featured online store setup with inventory management and payment processing.',
                'image' => 'https://example.com/ecommerce-platform.jpg',
                'stripe_product_id' => 'prod_' . Str::random(10),
                'type' => 'one_time',
                'active' => true,
                'is_archived' => false,
                'archived_date' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
