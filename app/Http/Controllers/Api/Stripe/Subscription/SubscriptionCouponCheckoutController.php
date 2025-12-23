<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Stripe\Subscription;

use App\Constants\SubscriptionMessages;
use App\Http\Controllers\Controller;
use App\Services\Stripe\Subscription\SubscriptionCouponCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for subscription checkout with coupon discount
 * Demo 3: Product with coupon
 */
class SubscriptionCouponCheckoutController extends Controller
{
    public function __construct(
        protected readonly SubscriptionCouponCheckoutService $checkoutService
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
     * Get all available coupons
     *
     * @return JsonResponse
     */
    public function getCoupons(): JsonResponse
    {
        try {
            $coupons = $this->checkoutService->getCoupons();

            return response()->json([
                'success' => true,
                'data' => $coupons,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Validate a coupon
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function validateCoupon(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'coupon_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Coupon ID is required',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $result = $this->checkoutService->validateCoupon($request->coupon_id);

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
     * Create a subscription checkout session with coupon
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createCheckoutSession(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'price_id' => 'required|string',
            'coupon_id' => 'required|string',
        ], [
            'price_id.required' => SubscriptionMessages::PRICE_ID_REQUIRED,
            'coupon_id.required' => 'Coupon ID is required',
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
                $request->coupon_id,
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
     * Calculate discount for a price with coupon
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function calculateDiscount(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|integer|min:0',
            'coupon_id' => 'required|string',
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
                $request->coupon_id
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
