<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Models\User;
use App\Services\Auth\SupabaseAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function __construct(
        private readonly SupabaseAuthService $supabaseAuth,
    ) {}

    /**
     * Register a new user.
     *
     * @OA\Post(
     *     path="/api/v1/auth/register",
     *     tags={"Auth"},
     *     summary="Register via Supabase",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password","password_confirmation"},
     *             @OA\Property(property="name", type="string", example="Emre"),
     *             @OA\Property(property="email", type="string", format="email", example="emre@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="Secret123!"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="Secret123!")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Registered")
     * )
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        if ($this->isDebugAuthBypassEnabled()) {
            return $this->debugRegister($request);
        }

        try {
            $authPayload = $this->supabaseAuth->register(
                email: $request->validated('email'),
                password: $request->validated('password'),
                name: $request->validated('name'),
            );
        } catch (RequestException $exception) {
            return $this->mapSupabaseException($exception);
        } catch (RuntimeException $exception) {
            return $this->errorResponse(
                $exception->getMessage(),
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        $user = $this->upsertShadowUser($authPayload['user']);

        return $this->createdResponse(
            data: [
                'user' => $this->formatUserResponse($user),
                'session' => $authPayload['session'],
            ],
            message: 'User registered successfully via Supabase',
        );
    }

    /**
     * Authenticate a user and return token.
     *
     * @OA\Post(
     *     path="/api/v1/auth/login",
     *     tags={"Auth"},
     *     summary="Login via Supabase",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", format="email", example="emre@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="Secret123!")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Logged in")
     * )
     */
    public function login(LoginRequest $request): JsonResponse
    {
        if ($this->isDebugAuthBypassEnabled()) {
            return $this->debugLogin($request);
        }

        try {
            $authPayload = $this->supabaseAuth->login(
                email: $request->validated('email'),
                password: $request->validated('password'),
            );
        } catch (RequestException $exception) {
            return $this->mapSupabaseException($exception);
        } catch (RuntimeException $exception) {
            return $this->errorResponse(
                $exception->getMessage(),
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        $user = $this->upsertShadowUser($authPayload['user']);

        return $this->successResponse(
            data: [
                'user' => $this->formatUserResponse($user),
                'session' => $authPayload['session'],
            ],
            message: 'Login successful',
        );
    }

    /**
     * Logout the authenticated user.
     *
     * @OA\Post(
     *     path="/api/v1/auth/logout",
     *     tags={"Auth"},
     *     summary="Logout current Supabase session",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Logged out")
     * )
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->bearerToken();
        if ($token === null || $token === '') {
            return $this->unauthorizedResponse('Missing bearer token.');
        }

        if ($this->isDebugAuthBypassEnabled() && $this->isDebugAccessToken($token)) {
            return $this->successResponse(
                message: 'Logout successful (debug auth bypass)',
            );
        }

        try {
            $this->supabaseAuth->logout($token);
        } catch (RequestException) {
            // idempotent logout: token may already be invalid/revoked
        } catch (RuntimeException $exception) {
            return $this->errorResponse(
                $exception->getMessage(),
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

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
     * @param  array<string, mixed>  $supabaseUser
     */
    private function upsertShadowUser(array $supabaseUser): User
    {
        $supabaseUserId = (string) ($supabaseUser['id'] ?? '');
        $email = is_string($supabaseUser['email'] ?? null) ? $supabaseUser['email'] : null;
        $name = $this->resolveDisplayName($supabaseUser, $email);

        $user = User::query()
            ->where('supabase_user_id', $supabaseUserId)
            ->when($email !== null, fn ($query) => $query->orWhere('email', $email))
            ->first();

        if ($user === null) {
            return User::create([
                'name' => $name,
                'email' => $email ?? 'user-'.uniqid().'@invalid.local',
                'password' => Hash::make(bin2hex(random_bytes(16))),
                'supabase_user_id' => $supabaseUserId,
            ]);
        }

        $user->fill(array_filter([
            'name' => $name,
            'email' => $email,
            'supabase_user_id' => $supabaseUserId,
        ], static fn ($value) => $value !== null && $value !== ''));
        $user->save();

        return $user->refresh();
    }

    /**
     * @param  array<string, mixed>  $supabaseUser
     */
    private function resolveDisplayName(array $supabaseUser, ?string $email): string
    {
        $metadata = is_array($supabaseUser['user_metadata'] ?? null) ? $supabaseUser['user_metadata'] : [];
        $name = $metadata['full_name'] ?? $metadata['name'] ?? null;

        if (is_string($name) && trim($name) !== '') {
            return trim($name);
        }

        if ($email !== null && str_contains($email, '@')) {
            return ucfirst(explode('@', $email)[0]);
        }

        return 'VoiceScribe User';
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
            'supabaseUserId' => $user->supabase_user_id,
        ];
    }

    private function mapSupabaseException(RequestException $exception): JsonResponse
    {
        $status = $exception->response?->status() ?? Response::HTTP_BAD_GATEWAY;
        $responseData = $exception->response?->json();
        $message = 'Supabase authentication request failed.';
        $errors = null;

        if (is_array($responseData)) {
            $message = (string) ($responseData['msg']
                ?? $responseData['error_description']
                ?? $responseData['message']
                ?? $message);
            $errors = $responseData;
        }

        return $this->errorResponse(
            $message,
            $status,
            $errors,
        );
    }

    private function debugRegister(RegisterRequest $request): JsonResponse
    {
        $email = (string) $request->validated('email');
        $name = $request->validated('name');

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $user = User::create([
                'name' => is_string($name) && trim($name) !== ''
                    ? trim($name)
                    : ucfirst(explode('@', $email)[0]),
                'email' => $email,
                'password' => Hash::make(bin2hex(random_bytes(16))),
                'supabase_user_id' => (string) Str::uuid(),
            ]);
        } elseif (is_string($name) && trim($name) !== '' && $user->name !== trim($name)) {
            $user->name = trim($name);
            if ($user->supabase_user_id === null || $user->supabase_user_id === '') {
                $user->supabase_user_id = (string) Str::uuid();
            }
            $user->save();
        }

        return $this->createdResponse(
            data: [
                'user' => $this->formatUserResponse($user->refresh()),
                'session' => $this->buildDebugSession($user),
            ],
            message: 'User registered (debug auth bypass)',
        );
    }

    private function debugLogin(LoginRequest $request): JsonResponse
    {
        $email = (string) $request->validated('email');

        $user = User::query()->where('email', $email)->first();
        if ($user === null) {
            return $this->unauthorizedResponse('Invalid credentials.');
        }

        if ($user->supabase_user_id === null || $user->supabase_user_id === '') {
            $user->supabase_user_id = (string) Str::uuid();
            $user->save();
        }

        return $this->successResponse(
            data: [
                'user' => $this->formatUserResponse($user->refresh()),
                'session' => $this->buildDebugSession($user),
            ],
            message: 'Login successful (debug auth bypass)',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDebugSession(User $user): array
    {
        return [
            'accessToken' => sprintf(
                'debug-local-%d-%s',
                $user->id,
                Str::random(40),
            ),
            'refreshToken' => null,
            'expiresIn' => 31536000,
            'tokenType' => 'bearer',
        ];
    }

    private function isDebugAuthBypassEnabled(): bool
    {
        return (bool) config('app.debug');
    }

    private function isDebugAccessToken(string $token): bool
    {
        return preg_match('/^debug-local-\d+-[A-Za-z0-9]+$/', $token) === 1;
    }
}
