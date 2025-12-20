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

        // Create new API token (optionally add expiry)
        $token = $user->createToken(
            name: 'api-token',
            abilities: ['*'],
            expiresAt: now()->addMinutes(config('constants.token_expiration_minutes'))
        )->plainTextToken;

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
        return [
            'message' => AuthMessages::LOGOUT_SUCCESS,
        ];
    }

    public function forgotPassword(array $data): array
    {
        $user = $this->userRepository->findByEmail($data['email']);

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => [AuthMessages::USER_NOT_FOUND],
            ]);
        }

        // Generate reset token
        $token = \Illuminate\Support\Str::random(64);

        // Store in password_resets table (assuming migration exists)
        \DB::table('password_resets')->updateOrInsert(
            ['email' => $data['email']],
            ['token' => $token, 'created_at' => now()]
        );

        // Send email (placeholder - mail needs to be configured)
        // \Mail::to($data['email'])->send(new ResetPasswordMail($token));

        return [
            'message' => 'Password reset link sent to your email.',
        ];
    }

    public function resetPassword(array $data): array
    {
        $resetRecord = \DB::table('password_resets')
            ->where('token', $data['token'])
            ->where('created_at', '>', now()->subHours(1)) // Token valid for 1 hour
            ->first();

        if (!$resetRecord) {
            throw ValidationException::withMessages([
                'token' => [AuthMessages::TOKEN_INVALID],
            ]);
        }

        $user = $this->userRepository->findByEmail($resetRecord->email);

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => [AuthMessages::USER_NOT_FOUND],
            ]);
        }

        // Update password
        $user->update(['password' => \Hash::make($data['password'])]);

        // Delete reset record
        \DB::table('password_resets')->where('email', $resetRecord->email)->delete();

        return [
            'message' => 'Password reset successfully.',
        ];
    }
}
