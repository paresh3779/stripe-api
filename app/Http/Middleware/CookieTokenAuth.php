<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Laravel\Sanctum\PersonalAccessToken;

class CookieTokenAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1️⃣ Read token from HttpOnly cookie
        $token = $request->cookie('api_token');

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // 2️⃣ Resolve Sanctum token
        $accessToken = PersonalAccessToken::findToken($token);

        // 3️⃣ Validate token & user
        if (
            !$accessToken ||
            !$accessToken->tokenable ||
            ($accessToken->expires_at && $accessToken->expires_at->isPast())
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // 4️⃣ Authenticate user into Laravel
        auth()->login($accessToken->tokenable);

        // 5️⃣ Continue request
        return $next($request);
    }
}
