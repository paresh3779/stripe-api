<?php 
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class RotateSanctumToken
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $user = $request->user();
        $token = $user?->currentAccessToken();

        if (!$token) {
            return $response;
        }

        if ($token->expires_at && now()->diffInMinutes($token->expires_at) <= 5) {

            // Revoke old token
            $token->delete();

            // Create new token
            $newToken = $user->createToken(
                'api-token',
                ['*'],
                now()->addMinutes(config('constants.token_expiration_minutes'))
            )->plainTextToken;

            // Attach new cookie
            $response->headers->setCookie(
                cookie(
                    'api_token',
                    $newToken,
                    config('constants.token_expiration_minutes'),
                    '/',
                    null,
                    true,
                    true,
                    false,
                    'None'
                )
            );
        }

        return $response;
    }
}
