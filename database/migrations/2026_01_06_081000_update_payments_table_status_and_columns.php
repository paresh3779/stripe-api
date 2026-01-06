<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update status enum to include all PaymentStatus values
        DB::statement("ALTER TABLE payments MODIFY COLUMN status ENUM('pending', 'succeeded', 'failed', 'refunded', 'partially_refunded', 'disputed', 'paid', 'cancelled') NOT NULL");

        // Update billing_reason enum to include subscription-related values
        DB::statement("ALTER TABLE payments MODIFY COLUMN billing_reason ENUM('one_time', 'subscription', 'addon', 'subscription_create', 'subscription_cycle', 'subscription_update', 'subscription_trial') NOT NULL");

        // Add stripe_subscription_id column for subscription payments
        Schema::table('payments', function (Blueprint $table) {
            $table->string('stripe_subscription_id')->nullable()->after('stripe_invoice_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove stripe_subscription_id column
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('stripe_subscription_id');
        });

        // Revert billing_reason enum
        DB::statement("ALTER TABLE payments MODIFY COLUMN billing_reason ENUM('one_time', 'subscription', 'addon') NOT NULL");

        // Revert status enum
        DB::statement("ALTER TABLE payments MODIFY COLUMN status ENUM('pending', 'succeeded', 'failed', 'refunded', 'partially_refunded') NOT NULL");
    }
};
