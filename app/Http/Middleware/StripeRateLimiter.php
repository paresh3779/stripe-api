<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rate limiting middleware specifically for Stripe API endpoints
 * Prevents abuse and protects against DDoS attacks
 */
class StripeRateLimiter
{
    /**
     * Default rate limit per minute
     */
    private const DEFAULT_RATE_LIMIT = 60;

    /**
     * Strict rate limit for sensitive operations (payments, subscriptions)
     */
    private const STRICT_RATE_LIMIT = 10;

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @param string $type Rate limit type: 'default' or 'strict'
     * @return Response
     */
    public function handle(Request $request, Closure $next, string $type = 'default'): Response
    {
        $key = $this->resolveRequestSignature($request);
        $maxAttempts = $type === 'strict' ? self::STRICT_RATE_LIMIT : self::DEFAULT_RATE_LIMIT;

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return response()->json([
                'success' => false,
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => RateLimiter::availableIn($key),
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        RateLimiter::hit($key, 60);

        $response = $next($request);

        return $response->withHeaders([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => RateLimiter::remaining($key, $maxAttempts),
        ]);
    }

    /**
     * Resolve request signature for rate limiting
     *
     * @param Request $request
     * @return string
     */
    protected function resolveRequestSignature(Request $request): string
    {
        $user = $request->user();
        
        if ($user) {
            return 'stripe_rate_limit:user:' . $user->id;
        }

        return 'stripe_rate_limit:ip:' . $request->ip();
    }
}
