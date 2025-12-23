<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Stripe\Subscription;

use App\Constants\SubscriptionMessages;
use App\Http\Controllers\Controller;
use App\Services\Stripe\Subscription\SubscriptionTrialCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for subscription checkout with trial period
 * Demo 2: Product with trial period
 */
class SubscriptionTrialCheckoutController extends Controller
{
    public function __construct(
        protected readonly SubscriptionTrialCheckoutService $checkoutService
    ) {}

    /**
     * Get all subscription products with trial periods
     *
     * @return JsonResponse
     */
    public function getProducts(): JsonResponse
    {
        try {
            $products = $this->checkoutService->getProductsWithTrial();

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
     * Get a single subscription product with trial info
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
     * Get trial information for a specific price
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getTrialInfo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'price_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => SubscriptionMessages::PRICE_ID_REQUIRED,
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $trialInfo = $this->checkoutService->getTrialInfo($request->price_id);

            return response()->json([
                'success' => true,
                'data' => $trialInfo,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Create a subscription checkout session with trial
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
