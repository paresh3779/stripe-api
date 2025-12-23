<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\RotateSanctumToken;
use App\Http\Middleware\CookieTokenAuth;
use App\Http\Middleware\StripeRateLimiter;
use App\Http\Middleware\ValidateStripeSignature;
use App\Http\Middleware\SanitizeInput;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
         $middleware->alias([
            'cookie.token' => CookieTokenAuth::class,
            'sanctum.rotate' => RotateSanctumToken::class,
            'stripe.rate_limit' => StripeRateLimiter::class,
            'stripe.signature' => ValidateStripeSignature::class,
            'sanitize' => SanitizeInput::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
