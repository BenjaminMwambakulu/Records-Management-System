<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
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

it('returns the authenticated user permissions', function () {
    $token = JwtTestHelper::sign(JwtTestHelper::claims('logto-perms'), $this->keys['private_pem'], $this->keys['kid']);

    $user = User::factory()->create(['logto_id' => 'logto-perms']);
    $role = Role::create(['name' => 'executive', 'guard_name' => 'logto']);
    Permission::findOrCreate('members.view', 'logto');
    Permission::findOrCreate('events.view', 'logto');
    $role->syncPermissions(['members.view', 'events.view']);
    $user->assignRole($role);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/me/permissions')
        ->assertOk()
        ->assertJsonPath('data.permissions', function ($perms) {
            return in_array('members.view', $perms) && in_array('events.view', $perms);
        });
});

it('returns assigned permissions for member role', function () {
    $token = JwtTestHelper::sign(JwtTestHelper::claims('logto-member-perms'), $this->keys['private_pem'], $this->keys['kid']);

    $user = User::factory()->create(['logto_id' => 'logto-member-perms']);
    $role = Role::create(['name' => 'member', 'guard_name' => 'logto']);
    Permission::findOrCreate('members.view', 'logto');
    $role->syncPermissions(['members.view']);
    $user->assignRole($role);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/me/permissions')
        ->assertOk()
        ->assertJsonPath('data.permissions', fn ($perms) => in_array('members.view', $perms));
});
