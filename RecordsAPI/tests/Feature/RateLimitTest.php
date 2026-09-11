<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\Support\JwtTestHelper;

uses(RefreshDatabase::class);

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

function rateLimitToken(array $keys, string $logtoId = 'logto-rl-user'): string
{
    return JwtTestHelper::sign(JwtTestHelper::claims($logtoId), $keys['private_pem'], $keys['kid']);
}

function createRateLimitUser(): User
{
    $user = User::factory()->create(['logto_id' => 'logto-rl-user']);
    $role = Role::create(['name' => 'executive', 'guard_name' => 'logto']);
    $user->syncRoles($role);

    return $user;
}

it('returns 422 when read rate limit is exceeded', function () {
    $token = rateLimitToken($this->keys);
    createRateLimitUser();

    for ($i = 0; $i < 121; $i++) {
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/me');
    }

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/me')
        ->assertStatus(429);
});

it('returns 422 when write rate limit is exceeded', function () {
    $token = rateLimitToken($this->keys);
    createRateLimitUser();

    for ($i = 0; $i < 31; $i++) {
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/members', [
                'first_name' => 'Test',
                'last_name' => "User {$i}",
                'email' => "test{$i}@example.com",
                'student_id' => "STU{$i}",
            ]);
    }

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/members', [
            'first_name' => 'Exceed',
            'last_name' => 'Limit',
            'email' => 'exceed@example.com',
            'student_id' => 'STU999',
        ])
        ->assertStatus(429);
});

it('includes Retry-After header when rate limited', function () {
    $token = rateLimitToken($this->keys);
    createRateLimitUser();

    for ($i = 0; $i < 121; $i++) {
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/me');
    }

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/me')
        ->assertStatus(429)
        ->assertHeader('Retry-After');
});
