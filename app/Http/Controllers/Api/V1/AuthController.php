<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Register a new user.
     *
     * @OA\Post(
     *     path="/api/v1/auth/register",
     *     tags={"Auth"},
     *     summary="Register user",
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"email","password","password_confirmation"},
     *
     *             @OA\Property(property="name", type="string", example="Emre"),
     *             @OA\Property(property="email", type="string", format="email", example="emre@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="Secret123!"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="Secret123!")
     *         )
     *     ),
     *
     *     @OA\Response(response=201, description="Registered")
     * )
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $email = (string) $payload['email'];
        $name = $payload['name'] ?? null;

        $user = User::create([
            'name' => is_string($name) && trim($name) !== ''
                ? trim($name)
                : ucfirst(explode('@', $email)[0]),
            'email' => $email,
            'password' => (string) $payload['password'],
        ]);

        $token = $user->createToken('voicescribe-mobile')->plainTextToken;

        return $this->createdResponse(
            data: [
                'user' => $this->formatUserResponse($user),
                'session' => $this->buildSessionPayload($token),
            ],
            message: 'User registered successfully',
        );
    }

    /**
     * Authenticate a user and return token.
     *
     * @OA\Post(
     *     path="/api/v1/auth/login",
     *     tags={"Auth"},
     *     summary="Login",
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"email","password"},
     *
     *             @OA\Property(property="email", type="string", format="email", example="emre@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="Secret123!")
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Logged in")
     * )
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $email = (string) $payload['email'];
        $password = (string) $payload['password'];

        $user = User::query()->where('email', $email)->first();
        if ($user === null) {
            return $this->unauthorizedResponse('Invalid credentials.');
        }

        if (! $this->isDebugAuthBypassEnabled() && ! Hash::check($password, $user->password)) {
            return $this->unauthorizedResponse('Invalid credentials.');
        }

        $token = $user->createToken('voicescribe-mobile')->plainTextToken;

        return $this->successResponse(
            data: [
                'user' => $this->formatUserResponse($user),
                'session' => $this->buildSessionPayload($token),
            ],
            message: $this->isDebugAuthBypassEnabled()
                ? 'Login successful (debug password bypass enabled)'
                : 'Login successful',
        );
    }

    /**
     * Logout the authenticated user.
     *
     * @OA\Post(
     *     path="/api/v1/auth/logout",
     *     tags={"Auth"},
     *     summary="Logout current session",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(response=200, description="Logged out")
     * )
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null) {
            return $this->unauthorizedResponse();
        }

        $request->user()?->currentAccessToken()?->delete();

        return $this->successResponse(
            message: 'Logout successful',
        );
    }

    /**
     * Return the current authenticated user.
     *
     * @OA\Get(
     *     path="/api/v1/auth/me",
     *     tags={"Auth"},
     *     summary="Get current user",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(response=200, description="Current user")
     * )
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null) {
            return $this->unauthorizedResponse();
        }

        return $this->successResponse(
            data: [
                'user' => $this->formatUserResponse($user),
            ],
            message: 'Current user fetched successfully',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function formatUserResponse(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSessionPayload(string $accessToken): array
    {
        return [
            'accessToken' => $accessToken,
            'refreshToken' => null,
            'expiresIn' => null,
            'tokenType' => 'bearer',
        ];
    }

    private function isDebugAuthBypassEnabled(): bool
    {
        // Decoupled from APP_DEBUG and hard-gated against production so a
        // misconfigured production deploy can never fall open. Only enabled
        // when explicitly opted in via AUTH_PASSWORD_BYPASS in a non-prod env.
        return ! app()->isProduction()
            && (bool) config('voicescribe.auth_password_bypass', false);
    }
}
