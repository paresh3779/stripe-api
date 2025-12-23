<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Stripe\Subscription;

use App\Constants\SubscriptionMessages;
use App\Http\Controllers\Controller;
use App\Services\Stripe\Subscription\SubscriptionCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for basic subscription checkout (monthly/yearly billing)
 * Demo 1: Product with monthly and yearly subscription
 */
class SubscriptionCheckoutController extends Controller
{
    public function __construct(
        protected readonly SubscriptionCheckoutService $checkoutService
    ) {}

    /**
     * Get all subscription products with pricing options
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
     * Get a single subscription product with all price options
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
     * Create a subscription checkout session
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createCheckoutSession(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'price_id' => 'required|string',
        ], [
            'price_id.required' => SubscriptionMessages::PRICE_ID_REQUIRED,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => SubscriptionMessages::PRICE_ID_REQUIRED,
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $user = $request->user();
            $session = $this->checkoutService->createCheckoutSession(
                $request->price_id,
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
}
