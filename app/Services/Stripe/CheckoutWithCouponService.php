<?php

namespace App\Services\Stripe;

use App\Models\User;
use App\Repositories\Stripe\ProductRepository;
use App\Repositories\Stripe\PriceRepository;
use App\Repositories\Stripe\CouponRepository;
use App\Repositories\Stripe\StripeCustomerRepository;
use Illuminate\Support\Collection;
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\Checkout\Session;

class CheckoutWithCouponService
{
    public function __construct(
        protected readonly ProductRepository $productRepository,
        protected readonly PriceRepository $priceRepository,
        protected readonly CouponRepository $couponRepository,
        protected readonly StripeCustomerRepository $customerRepository
    ) {
        Stripe::setApiKey(config('stripe.secret'));
    }

    public function getProducts(): Collection
    {
        return $this->productRepository->getOneTimeProducts();
    }

    public function getProductWithPrices(string $productId): object
    {
        $product = $this->productRepository->getProductWithPrices($productId);
        
        if (!$product) {
            throw new \Exception('Product not found');
        }

        return $product;
    }

    public function getAvailableCoupons(): Collection
    {
        return \App\Models\Coupon::where('active', true)
            ->where(function ($query) {
                $query->whereNull('valid_until')
                    ->orWhere('valid_until', '>', now());
            })
            ->where(function ($query) {
                $query->whereNull('max_redemptions')
                    ->orWhereRaw('times_redeemed < max_redemptions');
            })
            ->get();
    }

    public function createCheckoutSession(string $priceId, string $couponId, User $user): array
    {
        $price = $this->priceRepository->getPriceById($priceId);

        if (!$price) {
            throw new \Exception('Price not found');
        }

        if ($price->type !== 'one_time') {
            throw new \Exception('This price is not for one-time payment');
        }

        $coupon = $this->couponRepository->getCouponById($couponId);

        if (!$coupon) {
            throw new \Exception('Invalid or expired coupon');
        }

        $stripeCustomerId = $this->getOrCreateStripeCustomer($user);

        $sessionData = [
            'payment_method_types' => ['card'],
            'customer' => $stripeCustomerId,
            'line_items' => [
                    [
                    'price' => $price->stripe_price_id,
                    // 'price_data' => [
                    //         'currency' => $price->currency,
                    //         'product_data' => [
                    //             'name' => $price->product->name,
                    //             'description' => $price->product->description,
                    //         ],
                    //         'unit_amount' => $price->amount,
                    //     ],
                    'quantity' => 1,
                    ]
        ],
            'mode' => 'payment',
            'success_url' => config('app.frontend_url') . '/main/stripe-checkout/coupon',
            'cancel_url' => config('app.frontend_url') . '/main/stripe-checkout/coupon',
            // ✅ Coupon discount configuration
            'discounts' => [[
                'coupon' => $coupon->stripe_coupon_id,
            ]],
            // ✅ METADATA (top-level)
            'metadata' => [
                'user_id'        => (string) $user->id,
                'product_id'     => (string) $price->product->id,
                'price_id'       => (string) $price->id,
                'coupon_id'      => (string) $coupon->id,
            ],
            // 🔥 This saves the card for future use
            'payment_intent_data' => [
                'setup_future_usage' => 'off_session',
            ],
        ];

        if ($user->id) {
            $sessionData['client_reference_id'] = $user->id;
        }

        $session = Session::create($sessionData);

        return [
            'sessionId' => $session->id,
            'url' => $session->url,
        ];
    }

            /**
     * Get or create a Stripe customer for the user
     *
     * @param User $user
     * @return string Stripe customer ID
     * @throws \Exception
     */
    protected function getOrCreateStripeCustomer(User $user): string
    {
        $stripeCustomer = $this->customerRepository->findByUserId($user->id);

        if ($stripeCustomer) {
            return $stripeCustomer->stripe_customer_id;
        }

        try {
            $customer = Customer::create([
                'email' => $user->email,
                'name' => $user->first_name . ' ' . $user->last_name,
                'metadata' => [
                    'user_id' => $user->id,
                ],
            ]);

            $this->customerRepository->create(
                $user->id,
                $customer->id,
                $user->email
            );

            return $customer->id;
        } catch (\Exception $e) {
            throw new \Exception(SubscriptionMessages::CUSTOMER_CREATION_FAILED);
        }
    }
}
