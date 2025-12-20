<?php

namespace App\Services\Stripe;

use App\Repositories\Stripe\ProductRepository;
use App\Repositories\Stripe\PriceRepository;
use App\Repositories\Stripe\CouponRepository;
use Illuminate\Support\Collection;
use Stripe\Stripe;
use Stripe\Checkout\Session;

class CheckoutWithCouponService
{
    public function __construct(
        protected readonly ProductRepository $productRepository,
        protected readonly PriceRepository $priceRepository,
        protected readonly CouponRepository $couponRepository
    ) {
        Stripe::setApiKey(config('services.stripe.secret'));
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

    public function createCheckoutSession(string $priceId, string $couponId, ?string $userId = null): array
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

        $sessionData = [
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => $price->currency,
                    'product_data' => [
                        'name' => $price->product->name,
                        'description' => $price->product->description,
                    ],
                    'unit_amount' => $price->amount,
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => config('app.frontend_url') . '/main/stripe-checkout/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => config('app.frontend_url') . '/main/stripe-checkout/coupon',
            'discounts' => [[
                'coupon' => $coupon->stripe_coupon_id,
            ]],
        ];

        if ($userId) {
            $sessionData['client_reference_id'] = $userId;
        }

        $session = Session::create($sessionData);

        return [
            'sessionId' => $session->id,
            'url' => $session->url,
        ];
    }
}
