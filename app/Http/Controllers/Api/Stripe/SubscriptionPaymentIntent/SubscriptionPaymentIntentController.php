<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Stripe\SubscriptionPaymentIntent;

use App\Constants\SubscriptionPaymentIntentMessages;
use App\Http\Controllers\Controller;
use App\Services\Stripe\SubscriptionPaymentIntent\SubscriptionPaymentIntentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for basic subscription PaymentIntent (monthly/yearly billing)
 * Demo 1: Product with monthly and yearly subscription
 */
class SubscriptionPaymentIntentController extends Controller
{
    public function __construct(
        protected readonly SubscriptionPaymentIntentService $paymentIntentService
    ) {}

    /**
     * Get all subscription products with pricing options
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
     * Get a single subscription product with all price options
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
     * Create a SetupIntent for collecting payment method
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createSetupIntent(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'price_id' => 'required|string|exists:prices,id',
        ], [
            'price_id.required' => SubscriptionPaymentIntentMessages::PRICE_ID_REQUIRED,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => SubscriptionPaymentIntentMessages::PRICE_ID_REQUIRED,
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

            $result = $this->paymentIntentService->createSetupIntentForSubscription(
                $user,
                $request->price_id
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
     * Create a subscription with payment method
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createSubscription(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'price_id' => 'required|string|exists:prices,id',
            'payment_method_id' => 'required|string',
        ], [
            'price_id.required' => SubscriptionPaymentIntentMessages::PRICE_ID_REQUIRED,
            'payment_method_id.required' => SubscriptionPaymentIntentMessages::PAYMENT_METHOD_ID_REQUIRED,
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

            $result = $this->paymentIntentService->createSubscription(
                $request->price_id,
                $request->payment_method_id,
                $user
            );

            return response()->json([
                'success' => true,
                'message' => SubscriptionPaymentIntentMessages::PAYMENT_INTENT_CREATED,
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
     * Confirm subscription payment status
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
