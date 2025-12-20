<?php

namespace App\Http\Controllers\Api;

use App\Constants\AuthMessages;
use App\Http\Controllers\Controller;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authentication controller using Laravel Sanctum with HTTP-only cookies.
 */
class AuthController extends Controller
{
    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Register new user and issue authentication token.
     * POST /api/register
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ], [
            'first_name.required' => AuthMessages::FIRST_NAME_REQUIRED,
            'last_name.required' => AuthMessages::LAST_NAME_REQUIRED,
            'email.required' => AuthMessages::EMAIL_REQUIRED,
            'email.email' => AuthMessages::EMAIL_INVALID,
            'email.unique' => AuthMessages::EMAIL_UNIQUE,
            'password.required' => AuthMessages::PASSWORD_REQUIRED,
            'password.min' => AuthMessages::PASSWORD_MIN,
            //'password.confirmed' => AuthMessages::PASSWORD_CONFIRMED,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => AuthMessages::VALIDATION_FAILED,
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $result = $this->authService->register($request->only(['first_name', 'last_name', 'email', 'password']));

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'user' => $result['user'],
                    'token' => $result['token'],
                ],
            ], Response::HTTP_CREATED)->cookie(
                'api_token',
                $result['token'],
                60 * 24 * 7, // 7 days
                '/',
                null,
                true, // secure
                true, // httpOnly
                false,
                'Strict'
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Authenticate user and issue token (stored in HTTP-only cookie).
     * POST /api/login
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ], [
            'email.required' => AuthMessages::EMAIL_REQUIRED,
            'email.email' => AuthMessages::EMAIL_INVALID,
            'password.required' => AuthMessages::PASSWORD_REQUIRED,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => AuthMessages::VALIDATION_FAILED,
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $result = $this->authService->login($request->only(['email', 'password']));

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'user' => $result['user'],
                    //'token' => $result['token'],
                ],
            ])->cookie(
                'api_token',
                $result['token'],
                config('constants.token_expiration_minutes'),
                '/',
                config('session.domain'), // IMPORTANT
                true,
                true,
                false,
                'None' // REQUIRED for cross-domain
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_UNAUTHORIZED);
        }
    }

    /**
     * Refresh authentication token.
     * POST /api/refresh
     */
    public function refresh(Request $request): JsonResponse
    {
        try {
            $result = $this->authService->refresh($request);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'token' => $result['token'],
                ],
            ])->cookie(
                'api_token',
                $result['token'],
                60 * 24 * 7, // 7 days
                '/',
                config('session.domain'), // IMPORTANT
                true,
                true,
                false,
                'None' // REQUIRED for cross-domain
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_UNAUTHORIZED);
        }
    }

    /**
     * Logout user and revoke token.
     * POST /api/logout
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $result = $this->authService->logout($request);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
            ])->cookie('api_token', '', -1); // Expire cookie
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Send password reset email with token.
     * POST /api/forgot-password
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|max:255',
        ], [
            'email.required' => AuthMessages::EMAIL_REQUIRED,
            'email.email' => AuthMessages::EMAIL_INVALID,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => AuthMessages::VALIDATION_FAILED,
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $result = $this->authService->forgotPassword($request->only(['email']));

            return response()->json([
                'success' => true,
                'message' => $result['message'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Reset password using token from email.
     * POST /api/reset-password
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'password' => 'required|string|min:8',
            'confirmPassword' => 'required|string|same:password',
        ], [
            'token.required' => 'Token is required.',
            'password.required' => AuthMessages::PASSWORD_REQUIRED,
            'password.min' => AuthMessages::PASSWORD_MIN,
            'confirmPassword.required' => 'Confirm password is required.',
            'confirmPassword.same' => AuthMessages::PASSWORD_CONFIRMED,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => AuthMessages::VALIDATION_FAILED,
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $result = $this->authService->resetPassword($request->only(['token', 'password']));

            return response()->json([
                'success' => true,
                'message' => $result['message'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
