<?php

namespace App\Repositories\Stripe;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Collection;

class PaymentRepository
{
    /**
     * Create a new payment record
     *
     * @param array $data
     * @return Payment
     */
    public function create(array $data): Payment
    {
        return Payment::create($data);
    }

    /**
     * Find payment by Stripe payment intent ID
     *
     * @param string $paymentIntentId
     * @return Payment|null
     */
    public function findByPaymentIntentId(string $paymentIntentId): ?Payment
    {
        return Payment::where('stripe_payment_intent_id', $paymentIntentId)->first();
    }

    /**
     * Update payment record
     *
     * @param Payment $payment
     * @param array $data
     * @return Payment
     */
    public function update(Payment $payment, array $data): Payment
    {
        $payment->update($data);
        return $payment->fresh();
    }

    /**
     * Get user payments
     *
     * @param string $userId
     * @return Collection
     */
    public function getUserPayments(string $userId): Collection
    {
        return Payment::where('user_id', $userId)
            ->with(['product', 'price'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get payment by ID with relationships
     *
     * @param string $paymentId
     * @return Payment|null
     */
    public function findById(string $paymentId): ?Payment
    {
        return Payment::with(['user', 'product', 'price'])->find($paymentId);
    }

    /**
     * Find pending payment for user and price
     * Used to prevent duplicate payment intents
     *
     * @param string $userId
     * @param string $priceId
     * @return Payment|null
     */
    public function findPendingPayment(string $userId, string $priceId): ?Payment
    {
        return Payment::where('user_id', $userId)
            ->where('price_id', $priceId)
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subMinutes(30))
            ->whereNotNull('stripe_payment_intent_id')
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Get payments by status
     *
     * @param string $status
     * @param int $limit
     * @return Collection
     */
    public function getByStatus(string $status, int $limit = 100): Collection
    {
        return Payment::where('status', $status)
            ->with(['user', 'product'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
