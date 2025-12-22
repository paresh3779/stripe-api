<?php

namespace App\Repositories\Stripe;

use App\Models\StripeCustomer;
use App\Models\User;

class StripeCustomerRepository
{
    /**
     * Find Stripe customer by user ID
     *
     * @param string $userId
     * @return StripeCustomer|null
     */
    public function findByUserId(string $userId): ?StripeCustomer
    {
        return StripeCustomer::where('user_id', $userId)->first();
    }

    /**
     * Find Stripe customer by Stripe customer ID
     *
     * @param string $stripeCustomerId
     * @return StripeCustomer|null
     */
    public function findByStripeCustomerId(string $stripeCustomerId): ?StripeCustomer
    {
        return StripeCustomer::where('stripe_customer_id', $stripeCustomerId)->first();
    }

    /**
     * Create a new Stripe customer record
     *
     * @param string $userId
     * @param string $stripeCustomerId
     * @param string $email
     * @return StripeCustomer
     */
    public function create(string $userId, string $stripeCustomerId, string $email): StripeCustomer
    {
        return StripeCustomer::create([
            'user_id' => $userId,
            'stripe_customer_id' => $stripeCustomerId,
            'email' => $email,
        ]);
    }

    /**
     * Update Stripe customer record
     *
     * @param StripeCustomer $stripeCustomer
     * @param array $data
     * @return StripeCustomer
     */
    public function update(StripeCustomer $stripeCustomer, array $data): StripeCustomer
    {
        $stripeCustomer->update($data);
        return $stripeCustomer->fresh();
    }
}
