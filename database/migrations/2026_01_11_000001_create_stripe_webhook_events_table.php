<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This table tracks processed webhook events to ensure idempotency.
     * Stripe may send the same event multiple times, and we must handle this gracefully.
     */
    public function up(): void
    {
        Schema::create('stripe_webhook_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('stripe_event_id')->unique();
            $table->string('event_type');
            $table->string('status')->default('processing'); // processing, processed, failed
            $table->json('payload')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['event_type', 'status']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stripe_webhook_events');
    }
};
