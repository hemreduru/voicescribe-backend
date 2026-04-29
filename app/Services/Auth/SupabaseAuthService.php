<?php

namespace App\Services\Auth;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class SupabaseAuthService
{
    /**
     * Register user in Supabase Auth and return auth payload.
     *
     * @return array<string, mixed>
     */
    public function register(string $email, string $password, ?string $name = null): array
    {
        $payload = [
            'email' => $email,
            'password' => $password,
        ];

        if ($name !== null && $name !== '') {
            $payload['data'] = ['full_name' => $name];
        }

        $response = $this->request()
            ->post($this->authUrl('/signup'), $payload);

        return $this->handleAuthResponse($response->throw()->json());
    }

    /**
     * Login user with email/password and return session payload.
     *
     * @return array<string, mixed>
     */
    public function login(string $email, string $password): array
    {
        $response = $this->request()
            ->post($this->authUrl('/token?grant_type=password'), [
                'email' => $email,
                'password' => $password,
            ]);

        return $this->handleAuthResponse($response->throw()->json());
    }

    /**
     * Logout user session by access token.
     */
    public function logout(string $accessToken): void
    {
        $this->request(withBearer: $accessToken)
            ->post($this->authUrl('/logout'))
            ->throw();
    }

    /**
     * Validate token and fetch Supabase user.
     *
     * @return array<string, mixed>|null
     */
    public function getUserByAccessToken(?string $accessToken): ?array
    {
        if ($accessToken === null || $accessToken === '') {
            return null;
        }

        try {
            $response = $this->request(withBearer: $accessToken)
                ->get($this->authUrl('/user'));
            $data = $response->throw()->json();
        } catch (RequestException) {
            return null;
        }

        return is_array($data) ? $data : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function handleAuthResponse(array $payload): array
    {
        $user = $payload['user'] ?? null;
        if (! is_array($user) || empty($user['id'])) {
            throw new RuntimeException('Supabase auth response did not include user information.');
        }

        /** @var array<string, mixed>|null $session */
        $session = is_array($payload['session'] ?? null) ? $payload['session'] : null;
        $accessToken = $session['access_token'] ?? $payload['access_token'] ?? null;
        $refreshToken = $session['refresh_token'] ?? $payload['refresh_token'] ?? null;
        $expiresIn = $session['expires_in'] ?? $payload['expires_in'] ?? null;

        return [
            'user' => $user,
            'session' => [
                'accessToken' => is_string($accessToken) ? $accessToken : null,
                'refreshToken' => is_string($refreshToken) ? $refreshToken : null,
                'expiresIn' => is_int($expiresIn) ? $expiresIn : (is_numeric($expiresIn) ? (int) $expiresIn : null),
                'tokenType' => $session['token_type'] ?? $payload['token_type'] ?? 'bearer',
            ],
        ];
    }

    private function authUrl(string $path): string
    {
        $baseUrl = rtrim((string) config('services.supabase.url'), '/');
        if ($baseUrl === '') {
            throw new RuntimeException('SUPABASE_URL is not configured.');
        }

        return $baseUrl.'/auth/v1'.$path;
    }

    private function request(?string $withBearer = null)
    {
        $anonKey = (string) config('services.supabase.anon_key');
        if ($anonKey === '') {
            throw new RuntimeException('SUPABASE_ANON_KEY is not configured.');
        }

        $request = Http::acceptJson()
            ->asJson()
            ->withHeaders([
                'apikey' => $anonKey,
                'X-Client-Info' => 'voicescribe-backend/'.Str::lower(app()->version()),
            ]);

        if ($withBearer !== null && $withBearer !== '') {
            $request = $request->withToken($withBearer);
        }

        return $request;
    }
}

