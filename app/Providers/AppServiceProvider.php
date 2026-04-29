<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Auth\SupabaseAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Auth::viaRequest('supabase', function (Request $request): ?User {
            $token = $request->bearerToken();
            if ($token === null || $token === '') {
                return null;
            }

            if ($this->isDebugAuthBypassEnabled() && $this->isDebugAccessToken($token)) {
                return $this->resolveDebugUserFromToken($token);
            }

            /** @var SupabaseAuthService $supabaseAuth */
            $supabaseAuth = app(SupabaseAuthService::class);
            $supabaseUser = $supabaseAuth->getUserByAccessToken($token);

            if (! is_array($supabaseUser) || empty($supabaseUser['id'])) {
                return null;
            }

            $email = is_string($supabaseUser['email'] ?? null) ? $supabaseUser['email'] : null;
            $name = $this->resolveDisplayName($supabaseUser, $email);

            $user = User::query()
                ->where('supabase_user_id', $supabaseUser['id'])
                ->when($email !== null, fn ($query) => $query->orWhere('email', $email))
                ->first();

            if ($user === null) {
                if ($email === null) {
                    return null;
                }

                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make(bin2hex(random_bytes(16))),
                    'supabase_user_id' => $supabaseUser['id'],
                ]);
            } else {
                $user->fill(array_filter([
                    'name' => $name,
                    'email' => $email,
                    'supabase_user_id' => $supabaseUser['id'],
                ], static fn ($value) => $value !== null && $value !== ''));
                $user->save();
            }

            return $user;
        });
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

    private function isDebugAuthBypassEnabled(): bool
    {
        return (bool) config('app.debug');
    }

    private function isDebugAccessToken(string $token): bool
    {
        return preg_match('/^debug-local-\d+-[A-Za-z0-9]+$/', $token) === 1;
    }

    private function resolveDebugUserFromToken(string $token): ?User
    {
        if (preg_match('/^debug-local-(\d+)-[A-Za-z0-9]+$/', $token, $matches) !== 1) {
            return null;
        }

        $userId = (int) ($matches[1] ?? 0);
        if ($userId <= 0) {
            return null;
        }

        return User::query()->find($userId);
    }
}
