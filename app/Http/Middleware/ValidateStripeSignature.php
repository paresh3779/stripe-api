<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validates Stripe webhook signatures for security
 * Ensures webhooks are genuinely from Stripe
 */
class ValidateStripeSignature
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook_secret');

        if (!$sigHeader) {
            return response()->json([
                'success' => false,
                'message' => 'Missing Stripe signature header',
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!$webhookSecret) {
            return response()->json([
                'success' => false,
                'message' => 'Webhook secret not configured',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        try {
            Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (SignatureVerificationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid webhook signature',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
