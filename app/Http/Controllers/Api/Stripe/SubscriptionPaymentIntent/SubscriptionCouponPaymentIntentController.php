<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Stripe\SubscriptionPaymentIntent;

use App\Constants\SubscriptionPaymentIntentMessages;
use App\Http\Controllers\Controller;
use App\Services\Stripe\SubscriptionPaymentIntent\SubscriptionCouponPaymentIntentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for subscription PaymentIntent with coupon discount
 * Demo 3: Product with coupon
 */
class SubscriptionCouponPaymentIntentController extends Controller
{
    public function __construct(
        protected readonly SubscriptionCouponPaymentIntentService $paymentIntentService
    ) {}

    /**
     * Get all subscription products
     *
     * @return JsonResponse
     */
    public function getProducts(): JsonResponse
    {
        try {
            $products = $this->paymentIntentService->getProducts();

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
            $product = $this->paymentIntentService->getProductWithPrices($productId);

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
            $coupons = $this->paymentIntentService->getCoupons();

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
            $result = $this->paymentIntentService->validateCoupon($request->coupon_id);

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
            $result = $this->paymentIntentService->calculateCouponDiscount(
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

    /**
     * Create a SetupIntent for subscription with coupon
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createSetupIntent(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'price_id' => 'required|string|exists:prices,id',
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

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => SubscriptionPaymentIntentMessages::USER_NOT_AUTHENTICATED,
                ], Response::HTTP_UNAUTHORIZED);
            }

            $result = $this->paymentIntentService->createSetupIntentWithCoupon(
                $user,
                $request->price_id,
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

    /**
     * Create a subscription with coupon
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createSubscription(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'price_id' => 'required|string|exists:prices,id',
            'payment_method_id' => 'required|string',
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

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => SubscriptionPaymentIntentMessages::USER_NOT_AUTHENTICATED,
                ], Response::HTTP_UNAUTHORIZED);
            }

            $result = $this->paymentIntentService->createSubscriptionWithCoupon(
                $request->price_id,
                $request->payment_method_id,
                $request->coupon_id,
                $user
            );

            return response()->json([
                'success' => true,
                'message' => SubscriptionPaymentIntentMessages::COUPON_APPLIED,
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
     * Confirm subscription status
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function confirmSubscription(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'subscription_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription ID is required',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $result = $this->paymentIntentService->confirmSubscription($request->subscription_id);

            return response()->json([
                'success' => true,
                'message' => SubscriptionPaymentIntentMessages::PAYMENT_CONFIRMED,
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
