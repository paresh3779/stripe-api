<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users');
            $table->foreignUuid('product_id')->constrained('products');
            $table->foreignUuid('price_id')->constrained('prices');
            $table->string('stripe_payment_intent_id');
            $table->string('stripe_charge_id')->nullable();
            $table->string('stripe_invoice_id')->nullable();
            $table->text('description')->nullable();
            $table->bigInteger('amount');
            $table->string('currency', 10);
            $table->enum('status', ['pending', 'succeeded', 'failed', 'refunded', 'partially_refunded']);
            $table->string('payment_method', 50)->nullable();
            $table->enum('billing_reason', ['one_time', 'subscription', 'addon']);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
