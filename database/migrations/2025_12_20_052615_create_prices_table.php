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
        Schema::create('prices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained('products');
            $table->string('stripe_price_id')->unique();
            $table->text('description')->nullable();
            $table->bigInteger('amount');
            $table->string('currency', 10);
            $table->enum('type', ['one_time', 'recurring']);
            $table->enum('interval', ['day', 'week', 'month', 'year'])->nullable();
            $table->integer('interval_count')->default(1);
            $table->integer('trial_days')->nullable();
            $table->json('features')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prices');
    }
};
