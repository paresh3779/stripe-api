<?php

namespace App\Services\Stripe;

use App\Repositories\Stripe\ProductRepository;
use App\Repositories\Stripe\PriceRepository;
use App\Repositories\Stripe\PromoCodeRepository;
use Illuminate\Support\Collection;
use Stripe\Stripe;
use Stripe\Checkout\Session;

class CheckoutWithPromoCodeService
{
    public function __construct(
        protected readonly ProductRepository $productRepository,
        protected readonly PriceRepository $priceRepository,
        protected readonly PromoCodeRepository $promoCodeRepository
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

    public function validatePromoCode(string $code): array
    {
        $promoCode = $this->promoCodeRepository->getPromoCodeByCode($code);

        if (!$promoCode) {
            throw new \Exception('Invalid or expired promo code');
        }

        return [
            'valid' => true,
            'promoCode' => $promoCode,
            'coupon' => $promoCode->coupon,
        ];
    }

    public function createCheckoutSession(string $priceId, string $promoCode, ?string $userId = null): array
    {
        $price = $this->priceRepository->getPriceById($priceId);

        if (!$price) {
            throw new \Exception('Price not found');
        }

        if ($price->type !== 'one_time') {
            throw new \Exception('This price is not for one-time payment');
        }

        $promoCodeData = $this->promoCodeRepository->getPromoCodeByCode($promoCode);

        if (!$promoCodeData) {
            throw new \Exception('Invalid or expired promo code');
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
            'cancel_url' => config('app.frontend_url') . '/main/stripe-checkout/promocode',
            'discounts' => [[
                'promotion_code' => $promoCodeData->stripe_promotion_code_id,
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
