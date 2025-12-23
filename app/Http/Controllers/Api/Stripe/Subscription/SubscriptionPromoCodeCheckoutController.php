<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Stripe\Subscription;

use App\Constants\SubscriptionMessages;
use App\Http\Controllers\Controller;
use App\Services\Stripe\Subscription\SubscriptionPromoCodeCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for subscription checkout with promo code discount
 * Demo 4: Product with promocode
 */
class SubscriptionPromoCodeCheckoutController extends Controller
{
    public function __construct(
        protected readonly SubscriptionPromoCodeCheckoutService $checkoutService
    ) {}

    /**
     * Get all subscription products
     *
     * @return JsonResponse
     */
    public function getProducts(): JsonResponse
    {
        try {
            $products = $this->checkoutService->getProducts();

            return response()->json([
                'success' => true,
                'data' => $products,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Get a single subscription product
     *
     * @param string $productId
     * @return JsonResponse
     */
    public function getProduct(string $productId): JsonResponse
    {
        try {
            $product = $this->checkoutService->getProductWithPrices($productId);

            return response()->json([
                'success' => true,
                'data' => $product,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Validate a promo code
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function validatePromoCode(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Promo code is required',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $result = $this->checkoutService->validatePromoCode($request->code);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Create a subscription checkout session with promo code
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createCheckoutSession(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'price_id' => 'required|string',
            'promo_code' => 'required|string',
        ], [
            'price_id.required' => SubscriptionMessages::PRICE_ID_REQUIRED,
            'promo_code.required' => 'Promo code is required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $user = $request->user();
            $session = $this->checkoutService->createCheckoutSession(
                $request->price_id,
                $request->promo_code,
                $user
            );

            return response()->json([
                'success' => true,
                'message' => SubscriptionMessages::CHECKOUT_SESSION_CREATED,
                'data' => $session,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Calculate discount for a price with promo code
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function calculateDiscount(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|integer|min:0',
            'code' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $result = $this->checkoutService->calculateDiscount(
                $request->amount,
                $request->code
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
