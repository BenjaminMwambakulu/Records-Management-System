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
        'services.logto.m2m_app_id' => 'test-m2m',
        'services.logto.m2m_app_secret' => 'test-secret',
        'services.logto.management_api_resource' => 'https://api.test',
    ]);

    Cache::flush();

    $this->keys = JwtTestHelper::keyPair();

    Http::fake([
        'https://logto.test/oidc/jwks' => Http::response([
            'keys' => [JwtTestHelper::jwk($this->keys['public_pem'], $this->keys['kid'])],
        ]),
    ]);
});

function rolesToken(array $claims, array $keys): string
{
    return JwtTestHelper::sign($claims, $keys['private_pem'], $keys['kid']);
}

test('admin can assign and remove member roles locally and push them to Logto', function () {
    logtoAdminRole();
    Role::findOrCreate('member', 'logto');
    Role::create(['name' => 'event_officer', 'guard_name' => 'logto']);

    $admin = User::factory()->create(['logto_id' => 'logto-admin']);
    $admin->syncRoles(Role::where('name', 'admin')->where('guard_name', 'logto')->first());

    $target = User::factory()->create(['logto_id' => 'logto-member']);
    $target->syncRoles(Role::where('name', 'member')->where('guard_name', 'logto')->first());

    Http::fake([
        'https://logto.test/oidc/token' => Http::response(['access_token' => 'm2m-token'], 200),
        // Current roles for the target: just the "member" role id
        'https://logto.test/api/users/logto-member/roles' => Http::response(['items' => [
            ['id' => 'role-member', 'name' => 'member'],
        ]], 200),
        // Role list so we can resolve event_officer -> role-event-officer
        'https://logto.test/api/roles*' => Http::response(['items' => [
            ['id' => 'role-admin', 'name' => 'admin'],
            ['id' => 'role-member', 'name' => 'member'],
            ['id' => 'role-event-officer', 'name' => 'event_officer'],
        ], 'totalCount' => 3], 200),
        'https://logto.test/api/users/*' => Http::response([], 200),
    ]);

    $this->withHeader('Authorization', 'Bearer '.rolesToken(JwtTestHelper::claims('logto-admin'), $this->keys))
        ->postJson("/api/v1/members/{$target->id}/roles", [
            'roles' => ['member', 'event_officer'],
        ])
        ->assertOk()
        ->assertJsonPath('data.roles', ['member', 'event_officer']);

    expect($target->refresh()->getRoleNames()->all())->toContain('event_officer');

    $this->withHeader('Authorization', 'Bearer '.rolesToken(JwtTestHelper::claims('logto-admin'), $this->keys))
        ->deleteJson("/api/v1/members/{$target->id}/roles/event_officer")
        ->assertOk()
        ->assertJsonPath('data.roles', ['member']);

    expect($target->refresh()->getRoleNames()->all())->not->toContain('event_officer');
});
