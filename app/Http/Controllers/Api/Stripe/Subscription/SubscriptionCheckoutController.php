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
 * Controller for subscription checkout and management
 * Handles: products, checkout, subscriptions list, cancel, refund, invoices
 */
class SubscriptionCheckoutController extends Controller
{
    public function __construct(
        protected readonly SubscriptionCheckoutService $checkoutService
    ) {}

    // ==================== Products ====================

    /**
     * Get all subscription products with pricing options
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

    // ==================== Checkout ====================

    /**
     * Create a subscription checkout session
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

    // ==================== Subscription Management ====================

    /**
     * Get user's subscriptions
     */
    public function getSubscriptions(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $subscriptions = $this->checkoutService->getUserSubscriptions($user);

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
            $subscription = $this->checkoutService->getSubscription($subscriptionId, $user);

            if (!$subscription) {
                return response()->json([
                    'success' => false,
                    'message' => SubscriptionMessages::SUBSCRIPTION_NOT_FOUND,
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
     * Cancel subscription
     * If within 7 days and immediate=true, also processes refund
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
            $immediate = $request->boolean('immediate', false);
            
            $result = $this->checkoutService->cancelSubscription(
                $subscriptionId,
                $user,
                $immediate
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
            $subscriptionId = $request->query('subscription_id');
            
            $invoices = $this->checkoutService->getUserInvoices($user, $subscriptionId);

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
            $invoice = $this->checkoutService->getInvoice($invoiceId, $user);

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
            $pdfUrl = $this->checkoutService->getInvoicePdfUrl($invoiceId, $user);

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
