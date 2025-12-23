<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\User;
use App\Models\StripeCustomer;
use App\Repositories\Stripe\StripeCustomerRepository;
use Stripe\Customer;
use Stripe\Exception\ApiErrorException;

/**
 * Trait for Stripe customer management
 * Provides reusable customer creation and retrieval logic
 */
trait StripeCustomerTrait
{
    /**
     * Get or create a Stripe customer for the user
     *
     * @param User $user
     * @param StripeCustomerRepository $customerRepository
     * @return string Stripe customer ID
     * @throws \Exception
     */
    protected function getOrCreateCustomer(User $user, StripeCustomerRepository $customerRepository): string
    {
        $stripeCustomer = $customerRepository->findByUserId($user->id);

        if ($stripeCustomer) {
            return $stripeCustomer->stripe_customer_id;
        }

        try {
            $customer = Customer::create([
                'email' => $user->email,
                'name' => trim($user->first_name . ' ' . $user->last_name),
                'metadata' => [
                    'user_id' => $user->id,
                    'created_via' => 'api',
                ],
            ]);

            $customerRepository->create(
                $user->id,
                $customer->id,
                $user->email
            );

            return $customer->id;
        } catch (ApiErrorException $e) {
            throw new \Exception('Failed to create Stripe customer: ' . $e->getMessage());
        }
    }

    /**
     * Update Stripe customer information
     *
     * @param string $stripeCustomerId
     * @param array $data
     * @return Customer
     * @throws \Exception
     */
    protected function updateCustomer(string $stripeCustomerId, array $data): Customer
    {
        try {
            return Customer::update($stripeCustomerId, $data);
        } catch (ApiErrorException $e) {
            throw new \Exception('Failed to update Stripe customer: ' . $e->getMessage());
        }
    }
}
