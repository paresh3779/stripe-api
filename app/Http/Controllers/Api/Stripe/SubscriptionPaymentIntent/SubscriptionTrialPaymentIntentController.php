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
 * Controller for subscription PaymentIntent with 15-day trial period
 * Handles: Products, Checkout, Subscription Management, Payment Methods, Invoices
 */
class SubscriptionTrialPaymentIntentController extends Controller
{
    public function __construct(
        protected readonly SubscriptionTrialPaymentIntentService $paymentIntentService
    ) {}

    // ==================== Products ====================

    /**
     * Get all subscription products with trial periods
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

    // ==================== Payment Methods ====================

    /**
     * Get user's saved payment methods
     */
    public function getPaymentMethods(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => SubscriptionPaymentIntentMessages::USER_NOT_AUTHENTICATED,
                ], Response::HTTP_UNAUTHORIZED);
            }

            $paymentMethods = $this->paymentIntentService->getUserPaymentMethods($user);

            return response()->json([
                'success' => true,
                'data' => $paymentMethods,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Save a new payment method
     */
    public function savePaymentMethod(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'payment_method_id' => 'required|string',
            'set_default' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Payment method ID is required',
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

            $result = $this->paymentIntentService->savePaymentMethod(
                $request->payment_method_id,
                $user,
                $request->boolean('set_default', true)
            );

            return response()->json([
                'success' => true,
                'message' => 'Payment method saved successfully',
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
     * Delete a saved payment method
     */
    public function deletePaymentMethod(Request $request, string $paymentMethodId): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => SubscriptionPaymentIntentMessages::USER_NOT_AUTHENTICATED,
                ], Response::HTTP_UNAUTHORIZED);
            }

            $this->paymentIntentService->deletePaymentMethod($paymentMethodId, $user);

            return response()->json([
                'success' => true,
                'message' => 'Payment method deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    // ==================== Checkout ====================

    /**
     * Create a SetupIntent for trial subscription
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
     * Create a subscription with trial period (new payment method)
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
     * Create subscription using existing saved payment method
     */
    public function createSubscriptionWithSavedMethod(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'price_id' => 'required|string|exists:prices,id',
            'saved_payment_method_id' => 'required|string',
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

            $result = $this->paymentIntentService->createSubscriptionWithExistingPaymentMethod(
                $request->price_id,
                $request->saved_payment_method_id,
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

    // ==================== Subscription Management ====================

    /**
     * Get user's subscriptions
     */
    public function getSubscriptions(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => SubscriptionPaymentIntentMessages::USER_NOT_AUTHENTICATED,
                ], Response::HTTP_UNAUTHORIZED);
            }

            $subscriptions = $this->paymentIntentService->getUserSubscriptions($user);

            return response()->json([
                'success' => true,
                'data' => $subscriptions,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Get a single subscription
     */
    public function getSubscription(Request $request, string $subscriptionId): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => SubscriptionPaymentIntentMessages::USER_NOT_AUTHENTICATED,
                ], Response::HTTP_UNAUTHORIZED);
            }

            $subscription = $this->paymentIntentService->getSubscription($subscriptionId, $user);

            if (!$subscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'Subscription not found',
                ], Response::HTTP_NOT_FOUND);
            }

            return response()->json([
                'success' => true,
                'data' => $subscription,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Cancel subscription (with optional immediate cancellation and refund)
     */
    public function cancelSubscription(Request $request, string $subscriptionId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'immediate' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request parameters',
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

            $result = $this->paymentIntentService->cancelSubscription(
                $subscriptionId,
                $user,
                $request->boolean('immediate', false)
            );

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'subscription' => $result['subscription'],
                    'refund' => $result['refund'],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    // ==================== Invoice Management ====================

    /**
     * Get user's invoices
     */
    public function getInvoices(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => SubscriptionPaymentIntentMessages::USER_NOT_AUTHENTICATED,
                ], Response::HTTP_UNAUTHORIZED);
            }

            $subscriptionId = $request->query('subscription_id');
            $invoices = $this->paymentIntentService->getUserInvoices($user, $subscriptionId);

            return response()->json([
                'success' => true,
                'data' => $invoices,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Get a single invoice
     */
    public function getInvoice(Request $request, string $invoiceId): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => SubscriptionPaymentIntentMessages::USER_NOT_AUTHENTICATED,
                ], Response::HTTP_UNAUTHORIZED);
            }

            $invoice = $this->paymentIntentService->getInvoice($invoiceId, $user);

            if (!$invoice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice not found',
                ], Response::HTTP_NOT_FOUND);
            }

            return response()->json([
                'success' => true,
                'data' => $invoice,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Download invoice PDF (returns URL)
     */
    public function downloadInvoice(Request $request, string $invoiceId): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => SubscriptionPaymentIntentMessages::USER_NOT_AUTHENTICATED,
                ], Response::HTTP_UNAUTHORIZED);
            }

            $pdfUrl = $this->paymentIntentService->getInvoicePdfUrl($invoiceId, $user);

            if (!$pdfUrl) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice PDF not available',
                ], Response::HTTP_NOT_FOUND);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'pdf_url' => $pdfUrl,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
