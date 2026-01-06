<?php

namespace App\Http\Controllers\Api\Stripe;

use App\Http\Controllers\Controller;
use App\Services\Stripe\CheckoutWithCouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class CheckoutWithCouponController extends Controller
{
    protected CheckoutWithCouponService $checkoutService;

    public function __construct(CheckoutWithCouponService $checkoutService)
    {
        $this->checkoutService = $checkoutService;
    }

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

    public function getCoupons(): JsonResponse
    {
        try {
            $coupons = $this->checkoutService->getAvailableCoupons();

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

    public function createCheckoutSession(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'price_id' => 'required|string',
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
            $user = $request->user();
            $session = $this->checkoutService->createCheckoutSession(
                $request->price_id,
                $request->coupon_id,
                $user
            );

            return response()->json([
                'success' => true,
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
