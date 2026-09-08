<?php

namespace Tests\Support;

use Firebase\JWT\JWT;
use Illuminate\Support\Str;

class JwtTestHelper
{
    /**
     * @return array{private_pem: string, public_pem: string, kid: string}
     */
    public static function keyPair(): array
    {
        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        openssl_pkey_export($resource, $privatePem);
        $details = openssl_pkey_get_details($resource);

        return [
            'private_pem' => $privatePem,
            'public_pem' => $details['key'],
            'kid' => 'test-'.Str::random(6),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function jwk(string $publicPem, string $kid): array
    {
        $details = openssl_pkey_get_details(openssl_pkey_get_public($publicPem));

        return [
            'kty' => 'RSA',
            'kid' => $kid,
            'use' => 'sig',
            'alg' => 'RS256',
            'n' => JWT::urlsafeB64Encode($details['rsa']['n']),
            'e' => JWT::urlsafeB64Encode($details['rsa']['e']),
        ];
    }

    /**
     * @return array{private_pem: string, public_pem: string, kid: string, x: string, y: string}
     */
    public static function ecKeyPair(): array
    {
        $resource = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'secp384r1',
        ]);

        openssl_pkey_export($resource, $privatePem);
        $details = openssl_pkey_get_details($resource);

        return [
            'private_pem' => $privatePem,
            'public_pem' => $details['key'],
            'kid' => 'test-ec-'.Str::random(6),
            'x' => $details['ec']['x'],
            'y' => $details['ec']['y'],
        ];
    }

    /**
     * @param  array{public_pem: string, kid: string, x: string, y: string}  $key
     * @return array<string, mixed>
     */
    public static function ecJwk(array $key): array
    {
        return [
            'kty' => 'EC',
            'kid' => $key['kid'],
            'use' => 'sig',
            'alg' => 'ES384',
            'crv' => 'P-384',
            'x' => JWT::urlsafeB64Encode($key['x']),
            'y' => JWT::urlsafeB64Encode($key['y']),
        ];
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    public static function signEs384(array $claims, string $privatePem, string $kid): string
    {
        return JWT::encode($claims, $privatePem, 'ES384', $kid);
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    public static function sign(array $claims, string $privatePem, string $kid): string
    {
        return JWT::encode($claims, $privatePem, 'RS256', $kid);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function claims(string $logtoId, array $overrides = []): array
    {
        $now = time();

        return array_merge([
            'iss' => 'https://logto.test/oidc',
            'aud' => 'https://api.test',
            'sub' => $logtoId,
            'email' => 'member@example.com',
            'name' => 'Jane Doe',
            'username' => 'STU-0001',
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + 3600,
        ], $overrides);
    }
}
