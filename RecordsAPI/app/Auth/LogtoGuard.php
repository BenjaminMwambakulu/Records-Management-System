<?php

namespace App\Auth;

use App\Models\User;
use App\Services\RoleSyncService;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogtoGuard implements Guard
{
    use GuardHelpers;

    protected ?Request $resolvedRequest = null;

    public function __construct(
        UserProvider $provider,
        protected JwtVerifier $verifier,
        protected Container $app,
    ) {
        $this->provider = $provider;
    }

    public function user(): ?Authenticatable
    {
        $request = $this->app->make('request');

        if ($this->user !== null && $this->resolvedRequest === $request) {
            return $this->user;
        }

        $this->user = null;
        $this->resolvedRequest = $request;

        $token = $this->bearerToken($request);

        if (! $token) {
            return null;
        }

        try {
            $claims = $this->verifier->verify($token);
        } catch (JwtVerificationException $e) {
            Log::warning('Logto token verification failed', [
                'error' => $e->getMessage(),
                'path' => $request->path(),
            ]);

            return null;
        }

        $logtoId = $claims['sub'] ?? null;

        if (! is_string($logtoId) || $logtoId === '') {
            return null;
        }

        $user = User::firstOrCreate(
            ['logto_id' => $logtoId],
            $this->provisionableFields($claims),
        );

        if (! empty($claims['roles'])) {
            try {
                app(RoleSyncService::class)->syncUserAndRoles($user, $claims['roles']);
            } catch (\Throwable $e) {
                Log::warning('Failed to sync Logto roles during authentication', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->user = $user;
    }

    public function validate(array $credentials = []): bool
    {
        return $this->user() !== null;
    }

    protected function bearerToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');

        if (str_starts_with($header, 'Bearer ')) {
            return trim(substr($header, 7));
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $claims
     * @return array<string, mixed>
     */
    protected function provisionableFields(array $claims): array
    {
        $name = $this->cleanClaim($claims, 'name');
        $parts = $name === null ? [] : array_values(array_filter(
            preg_split('/\s+/', $name) ?: [],
            fn (string $part) => $part !== '' && $part !== 'undefined' && $part !== 'null',
        ));

        return [
            'student_id' => null,
            'first_name' => $parts[0] ?? $this->cleanClaim($claims, 'username'),
            'last_name' => count($parts) > 1 ? $parts[count($parts) - 1] : null,
            'email' => $this->cleanClaim($claims, 'email'),
        ];
    }

    protected function cleanClaim(array $claims, string $key): ?string
    {
        $value = $claims[$key] ?? null;

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '' || $value === 'undefined' || $value === 'null') {
            return null;
        }

        return $value;
    }
}
