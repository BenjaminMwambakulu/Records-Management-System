<?php

namespace App\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class JwtVerifier
{
    protected const CACHE_KEY = 'logto.jwks';

    /**
     * @return array<string, mixed>
     *
     * @throws JwtVerificationException
     */
    public function verify(string $token): array
    {
        try {
            $jwk = $this->resolveSigningKey($token);
            $decoded = (array) JWT::decode($token, JWK::parseKey($jwk));
        } catch (JwtVerificationException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new JwtVerificationException("Unable to verify access token: {$e->getMessage()}", 0, $e);
        }

        $this->assertClaims($decoded);

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JwtVerificationException
     */
    protected function resolveSigningKey(string $token): array
    {
        $header = $this->tokenHeader($token);
        $kid = $header['kid'] ?? null;

        $keys = $this->jwks()['keys'] ?? [];

        if ($keys === []) {
            throw new JwtVerificationException('Logto JWKS contains no signing keys.');
        }

        if (! $kid) {
            return $keys[0];
        }

        foreach ($keys as $candidate) {
            if (($candidate['kid'] ?? null) === $kid) {
                return $candidate;
            }
        }

        throw new JwtVerificationException('No signing key matching the token header was found in the JWKS.');
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JwtVerificationException
     */
    protected function jwks(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addHour(), function (): array {
            $response = $this->fetchJwks();

            if ($response->failed()) {
                Cache::forget(self::CACHE_KEY);
                $response = $this->fetchJwks();
            }

            if ($response->failed()) {
                throw new JwtVerificationException('Failed to fetch the Logto JWKS.');
            }

            return $response->json();
        });
    }

    protected function fetchJwks(): Response
    {
        return Http::timeout(5)
            ->connectTimeout(5)
            ->get($this->jwksUrl());
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JwtVerificationException
     */
    protected function tokenHeader(string $token): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new JwtVerificationException('Malformed access token.');
        }

        $header = json_decode(JWT::urlsafeB64Decode($parts[0]), true);

        if (! is_array($header)) {
            throw new JwtVerificationException('Malformed token header.');
        }

        return $header;
    }

    /**
     * @param  array<string, mixed>  $claims
     *
     * @throws JwtVerificationException
     */
    protected function assertClaims(array $claims): void
    {
        if (($claims['iss'] ?? null) !== $this->issuer()) {
            throw new JwtVerificationException('Access token issuer does not match.');
        }

        $audience = $claims['aud'] ?? [];
        $audiences = is_array($audience) ? $audience : [$audience];

        if (! in_array($this->audience(), $audiences, true)) {
            throw new JwtVerificationException('Access token audience does not match.');
        }
    }

    protected function jwksUrl(): string
    {
        return rtrim(config('services.logto.endpoint'), '/').'/oidc/jwks';
    }

    protected function issuer(): string
    {
        return config('services.logto.issuer') ?: rtrim(config('services.logto.endpoint'), '/').'/oidc';
    }

    protected function audience(): string
    {
        return (string) config('services.logto.api_resource');
    }
}
