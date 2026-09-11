<?php

use App\Auth\JwtVerificationException;
use App\Auth\JwtVerifier;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Support\JwtTestHelper;

beforeEach(function () {
    config([
        'services.logto.endpoint' => 'https://logto.test',
        'services.logto.issuer' => 'https://logto.test/oidc',
        'services.logto.api_resource' => 'https://api.test',
    ]);

    Cache::flush();

    $this->keys = JwtTestHelper::keyPair();

    Http::fake([
        'https://logto.test/oidc/jwks' => Http::response([
            'keys' => [JwtTestHelper::jwk($this->keys['public_pem'], $this->keys['kid'])],
        ]),
    ]);
});

test('verifies a valid RS256 token and returns its claims', function () {
    $token = JwtTestHelper::sign(
        JwtTestHelper::claims('logto-123', ['email' => 'alice@example.com']),
        $this->keys['private_pem'],
        $this->keys['kid'],
    );

    $claims = app(JwtVerifier::class)->verify($token);

    expect($claims['sub'])->toBe('logto-123')
        ->and($claims['email'])->toBe('alice@example.com');
});

test('verifies an ES384 token signed by an EC P-384 key (Logto tenant shape)', function () {
    $ec = JwtTestHelper::ecKeyPair();

    config(['services.logto.endpoint' => 'https://ec-logto.test']);

    Http::fake([
        'https://ec-logto.test/oidc/jwks' => Http::response([
            'keys' => [JwtTestHelper::ecJwk($ec)],
        ]),
    ]);

    $token = JwtTestHelper::signEs384(
        JwtTestHelper::claims('logto-ec'),
        $ec['private_pem'],
        $ec['kid'],
    );

    $claims = app(JwtVerifier::class)->verify($token);

    expect($claims['sub'])->toBe('logto-ec');
});

test('rejects a token signed with an unknown private key', function () {
    $attackerKeys = JwtTestHelper::keyPair();
    $token = JwtTestHelper::sign(
        JwtTestHelper::claims('logto-123'),
        $attackerKeys['private_pem'],
        $attackerKeys['kid'],
    );

    expect(fn () => app(JwtVerifier::class)->verify($token))
        ->toThrow(JwtVerificationException::class);
});

test('rejects a token with a mismatched audience', function () {
    $token = JwtTestHelper::sign(
        JwtTestHelper::claims('logto-123', ['aud' => 'https://some-other-resource']),
        $this->keys['private_pem'],
        $this->keys['kid'],
    );

    expect(fn () => app(JwtVerifier::class)->verify($token))
        ->toThrow(JwtVerificationException::class);
});

test('rejects a token with a mismatched issuer', function () {
    $token = JwtTestHelper::sign(
        JwtTestHelper::claims('logto-123', ['iss' => 'https://evil.test/oidc']),
        $this->keys['private_pem'],
        $this->keys['kid'],
    );

    expect(fn () => app(JwtVerifier::class)->verify($token))
        ->toThrow(JwtVerificationException::class);
});

test('rejects an expired token', function () {
    $token = JwtTestHelper::sign(
        JwtTestHelper::claims('logto-123', ['exp' => time() - 60]),
        $this->keys['private_pem'],
        $this->keys['kid'],
    );

    expect(fn () => app(JwtVerifier::class)->verify($token))
        ->toThrow(JwtVerificationException::class);
});

test('retries the JWKS fetch once before failing authentication', function () {
    config(['services.logto.endpoint' => 'https://retry-logto.test']);

    $token = JwtTestHelper::sign(
        JwtTestHelper::claims('logto-123'),
        $this->keys['private_pem'],
        $this->keys['kid'],
    );

    Http::fakeSequence('https://retry-logto.test/oidc/jwks')
        ->push([], 503)
        ->push(['keys' => [JwtTestHelper::jwk($this->keys['public_pem'], $this->keys['kid'])]], 200);

    $claims = app(JwtVerifier::class)->verify($token);

    expect($claims['sub'])->toBe('logto-123')
        ->and(Http::recorded())->toHaveCount(2);
});

test('fails authentication when the JWKS remains unreachable', function () {
    config(['services.logto.endpoint' => 'https://retry-logto.test']);

    $token = JwtTestHelper::sign(
        JwtTestHelper::claims('logto-123'),
        $this->keys['private_pem'],
        $this->keys['kid'],
    );

    Http::fakeSequence('https://retry-logto.test/oidc/jwks')
        ->push([], 503)
        ->push([], 503);

    expect(fn () => app(JwtVerifier::class)->verify($token))
        ->toThrow(JwtVerificationException::class);
});
