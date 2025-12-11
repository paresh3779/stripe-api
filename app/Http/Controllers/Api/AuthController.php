<?php

namespace App\Http\Controllers\Api;

use App\Constants\AuthMessages;
use App\Http\Controllers\Controller;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

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
                    'token' => $result['token'],
                ],
            ])->cookie(
                'api_token',
                $result['token'],
                60 * 24 * 7,
                '/',
                null,
                true,
                true,
                false,
                'Strict'
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_UNAUTHORIZED);
        }
    }

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
                60 * 24 * 7,
                '/',
                null,
                true,
                true,
                false,
                'Strict'
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_UNAUTHORIZED);
        }
    }

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
}
