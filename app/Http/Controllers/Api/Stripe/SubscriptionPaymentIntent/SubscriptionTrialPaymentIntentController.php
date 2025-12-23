<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Stripe\SubscriptionPaymentIntent;

use App\Constants\SubscriptionPaymentIntentMessages;
use App\Http\Controllers\Controller;
use App\Services\Stripe\SubscriptionPaymentIntent\SubscriptionTrialPaymentIntentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for subscription PaymentIntent with trial period
 * Demo 2: Product with trial period
 */
class SubscriptionTrialPaymentIntentController extends Controller
{
    public function __construct(
        protected readonly SubscriptionTrialPaymentIntentService $paymentIntentService
    ) {}

    /**
     * Get all subscription products with trial periods
     *
     * @return JsonResponse
     */
    public function getProducts(): JsonResponse
    {
        try {
            $products = $this->paymentIntentService->getProductsWithTrial();

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
     * Get trial information for a specific price
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getTrialInfo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'price_id' => 'required|string|exists:prices,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => SubscriptionPaymentIntentMessages::PRICE_ID_REQUIRED,
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $trialInfo = $this->paymentIntentService->getTrialInfo($request->price_id);

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
     * Create a SetupIntent for trial subscription
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createSetupIntent(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'price_id' => 'required|string|exists:prices,id',
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

            $result = $this->paymentIntentService->createSetupIntentForTrial(
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
     * Create a subscription with trial period
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createSubscription(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'price_id' => 'required|string|exists:prices,id',
            'payment_method_id' => 'required|string',
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

            $result = $this->paymentIntentService->createSubscriptionWithTrial(
                $request->price_id,
                $request->payment_method_id,
                $user
            );

            return response()->json([
                'success' => true,
                'message' => SubscriptionPaymentIntentMessages::TRIAL_STARTED,
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
