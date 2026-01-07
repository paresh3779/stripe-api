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
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignUuid('subscription_id')->nullable()->constrained('subscriptions')->onDelete('set null');
            $table->string('stripe_invoice_id')->unique();
            $table->string('stripe_customer_id');
            $table->string('number')->nullable(); // Invoice number from Stripe
            $table->enum('status', [
                'draft',
                'open',
                'paid',
                'uncollectible',
                'void'
            ])->default('draft');
            $table->bigInteger('amount_due');
            $table->bigInteger('amount_paid')->default(0);
            $table->bigInteger('amount_remaining')->default(0);
            $table->bigInteger('subtotal');
            $table->bigInteger('total');
            $table->bigInteger('tax')->default(0);
            $table->string('currency', 10);
            $table->text('description')->nullable();
            $table->string('hosted_invoice_url')->nullable();
            $table->string('invoice_pdf')->nullable();
            $table->timestamp('due_date')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->json('line_items')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('stripe_customer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
