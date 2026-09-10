<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
    Role::findOrCreate('member', 'web');
    $this->keys = JwtTestHelper::keyPair();

    Http::fake([
        'https://logto.test/oidc/jwks' => Http::response([
            'keys' => [JwtTestHelper::jwk($this->keys['public_pem'], $this->keys['kid'])],
        ]),
    ]);
});

it('returns a 422 validation error when the avatar is not an image', function () {
    $token = JwtTestHelper::sign(JwtTestHelper::claims('logto-avatar'), $this->keys['private_pem'], $this->keys['kid']);

    User::factory()->create(['logto_id' => 'logto-avatar']);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/me/avatar', [
            'avatar' => UploadedFile::fake()->create('avatar.txt', 100),
        ])
        ->assertStatus(422);
});

it('returns a 422 validation error when the avatar exceeds the size limit', function () {
    $token = JwtTestHelper::sign(JwtTestHelper::claims('logto-avatar-big'), $this->keys['private_pem'], $this->keys['kid']);

    User::factory()->create(['logto_id' => 'logto-avatar-big']);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/me/avatar', [
            'avatar' => UploadedFile::fake()->image('avatar.png')->size(6000),
        ])
        ->assertStatus(422);
});