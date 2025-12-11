<?php

namespace App\Services;

use App\Constants\AuthMessages;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class AuthService
{
    protected UserRepository $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function register(array $data): array
    {
        if ($this->userRepository->existsByEmail($data['email'])) {
            throw ValidationException::withMessages([
                'email' => [AuthMessages::USER_ALREADY_EXISTS],
            ]);
        }

        $user = $this->userRepository->create($data);
        $token = $user->createToken('api-token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
            'message' => AuthMessages::REGISTER_SUCCESS,
        ];
    }

    public function login(array $data): array
    {
        $user = $this->userRepository->findByEmail($data['email']);

        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => [AuthMessages::INVALID_CREDENTIALS],
            ]);
        }

        // Revoke existing tokens for security
        $user->tokens()->delete();

        $token = $user->createToken('api-token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
            'message' => AuthMessages::LOGIN_SUCCESS,
        ];
    }

    public function refresh(Request $request): array
    {
        $user = $request->user();

        if (!$user) {
            throw ValidationException::withMessages([
                'token' => [AuthMessages::TOKEN_INVALID],
            ]);
        }

        // Create new token, delete old one
        $request->user()->currentAccessToken()->delete();
        $newToken = $user->createToken('api-token')->plainTextToken;

        return [
            'token' => $newToken,
            'message' => AuthMessages::REFRESH_SUCCESS,
        ];
    }

    public function logout(Request $request): array
    {
        $request->user()->currentAccessToken()->delete();

        return [
            'message' => AuthMessages::LOGOUT_SUCCESS,
        ];
    }
}
