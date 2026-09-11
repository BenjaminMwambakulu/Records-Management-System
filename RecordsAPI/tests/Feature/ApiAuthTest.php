<?php

use App\Jobs\SyncMemberToLogto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
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

function logtoToken(array $claims, array $keys): string
{
    return JwtTestHelper::sign($claims, $keys['private_pem'], $keys['kid']);
}

test('GET /api/v1/me without a token returns 401', function () {
    $this->getJson('/api/v1/me')->assertStatus(401);
});

test('GET /api/v1/me returns the authenticated member', function () {
    $user = User::factory()->create(['logto_id' => 'logto-123']);
    $token = logtoToken(JwtTestHelper::claims('logto-123'), $this->keys);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.student_id', $user->student_id);
});

test('GET /api/v1/me auto-provisions an unknown member', function () {
    $token = logtoToken(JwtTestHelper::claims('logto-456', [
        'email' => 'new@example.com',
        'name' => 'New Person',
        'username' => 'STU-9999',
    ]), $this->keys);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'new@example.com')
        ->assertJsonPath('data.first_name', 'New')
        ->assertJsonPath('data.last_name', 'Person')
        ->assertJsonPath('data.student_id', null);

    expect(User::where('logto_id', 'logto-456')->exists())->toBeTrue();
});

test('GET /api/v1/me uses the username as the name when the name is garbage', function () {
    $token = logtoToken(JwtTestHelper::claims('logto-noname', [
        'name' => 'undefined undefined',
        'username' => 'Vamp2o5',
    ]), $this->keys);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.student_id', null);

    $user = User::where('logto_id', 'logto-noname')->first();
    expect($user)->not->toBeNull()
        ->and($user->student_id)->toBeNull()
        ->and($user->first_name)->toBe('Vamp2o5')
        ->and($user->last_name)->toBeNull()
        ->and($user->getRoleNames()->all())->toBe(['member']);
});

test('GET /api/v1/me auto-provisions when the token lacks profile claims', function () {
    $claims = JwtTestHelper::claims('logto-minimal', ['name' => 'undefined undefined']);
    unset($claims['username'], $claims['email']);

    $this->withHeader('Authorization', 'Bearer '.logtoToken($claims, $this->keys))
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.logto_id', 'logto-minimal')
        ->assertJsonPath('data.student_id', null);

    $user = User::where('logto_id', 'logto-minimal')->first();
    expect($user)->not->toBeNull()
        ->and($user->student_id)->toBeNull()
        ->and($user->first_name)->toBeNull()
        ->and($user->last_name)->toBeNull()
        ->and($user->email)->toBeNull();
});

test('GET /api/v1/me strips garbage profile claims and falls back to the username', function () {
    $this->withHeader('Authorization', 'Bearer '.logtoToken(JwtTestHelper::claims('logto-garbage', [
        'name' => 'undefined undefined',
        'email' => 'garbage@example.com',
    ]), $this->keys))
        ->getJson('/api/v1/me')
        ->assertOk();

    $user = User::where('logto_id', 'logto-garbage')->first();
    expect($user->student_id)->toBeNull()
        ->and($user->first_name)->toBe('STU-0001')
        ->and($user->last_name)->toBeNull()
        ->and($user->email)->toBe('garbage@example.com');
});

test('GET /api/v1/me rejects an invalid token', function () {
    $this->withHeader('Authorization', 'Bearer not-a-jwt')
        ->getJson('/api/v1/me')
        ->assertStatus(401);
});

test('GET /api/v1/me syncs Logto roles into local roles', function () {
    $token = logtoToken(JwtTestHelper::claims('logto-roles', [
        'roles' => [
            ['id' => 'rid-super', 'name' => 'super_admin'],
            ['id' => 'rid-member', 'name' => 'member'],
        ],
    ]), $this->keys);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/me')
        ->assertOk();

    $user = User::where('logto_id', 'logto-roles')->first();
    expect($user)->not->toBeNull()
        ->and($user->hasRole('superadmin', 'logto'))->toBeTrue()
        ->and($user->hasRole('member', 'logto'))->toBeTrue();

    $roles = collect($user->getRoleNames())->sort()->values()->all();
    expect($roles)->toBe(['member', 'superadmin']);

    expect(Role::where('name', 'superadmin')->where('guard_name', 'logto')->exists())->toBeTrue();
});

test('GET /api/v1/me maps Logto role ids via role_mapping', function () {
    config(['services.logto.role_mapping' => ['admin' => 'logto-admin-id']]);

    $this->withHeader('Authorization', 'Bearer '.logtoToken(JwtTestHelper::claims('logto-mapped', [
        'roles' => [['id' => 'logto-admin-id', 'name' => 'member']],
    ]), $this->keys))
        ->getJson('/api/v1/me')
        ->assertOk();

    $user = User::where('logto_id', 'logto-mapped')->first();
    expect($user->hasRole('admin', 'logto'))->toBeTrue();
});

test('GET /api/v1/me enriches the profile from the Logto management API', function () {
    $user = User::factory()->create([
        'logto_id' => 'logto-rich',
        'student_id' => null,
        'first_name' => null,
        'last_name' => null,
    ]);

    Http::fake([
        'https://logto.test/oidc/jwks' => Http::response([
            'keys' => [JwtTestHelper::jwk($this->keys['public_pem'], $this->keys['kid'])],
        ], 200),
        'https://logto.test/oidc/token' => Http::response(['access_token' => 'm2m-token'], 200),
        'https://logto.test/api/users/*' => Http::response([
            'id' => 'logto-rich',
            'username' => 'Vamp2o5',
            'primaryEmail' => 'bit-023-22@must.ac.mw',
            'name' => 'Santiago Vander',
            'avatar' => 'https://example.com/avatar.png',
        ], 200),
    ]);

    $this->withHeader('Authorization', 'Bearer '.logtoToken(JwtTestHelper::claims('logto-rich'), $this->keys))
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.avatar', 'https://example.com/avatar.png')
        ->assertJsonPath('data.first_name', 'Santiago')
        ->assertJsonPath('data.last_name', 'Vander')
        ->assertJsonPath('data.full_name', 'Santiago Vander')
        ->assertJsonPath('data.student_id', null)
        ->assertJsonPath('data.email', $user->email);
});

test('GET /api/v1/me still works when the management API is unavailable', function () {
    $user = User::factory()->create(['logto_id' => 'logto-nom2m']);

    Http::fake([
        'https://logto.test/oidc/jwks' => Http::response([
            'keys' => [JwtTestHelper::jwk($this->keys['public_pem'], $this->keys['kid'])],
        ], 200),
        'https://logto.test/oidc/token' => Http::response(['access_token' => 'm2m-token'], 200),
        'https://logto.test/api/users/*' => Http::response([], 403),
    ]);

    $this->withHeader('Authorization', 'Bearer '.logtoToken(JwtTestHelper::claims('logto-nom2m'), $this->keys))
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonPath('data.student_id', $user->student_id)
        ->assertJsonMissingPath('data.avatar');
});

test('member routes require authentication', function () {
    $this->getJson('/api/v1/members')->assertStatus(401);
    $this->postJson('/api/v1/members', [])->assertStatus(401);
});

test('only admins can create members', function () {
    $adminRole = logtoAdminRole();
    $memberRole = Role::findOrCreate('member', 'logto');

    $admin = User::factory()->create(['logto_id' => 'logto-admin']);
    $admin->syncRoles($adminRole);

    Queue::fake();

    $this->withHeader('Authorization', 'Bearer '.logtoToken(JwtTestHelper::claims('logto-admin'), $this->keys))
        ->postJson('/api/v1/members', [
            'logto_id' => 'logto-alice',
            'student_id' => 'STU-0003',
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'email' => 'alice@example.com',
        ])
        ->assertCreated();

    Queue::assertPushed(SyncMemberToLogto::class);

    $member = User::factory()->create(['logto_id' => 'logto-member']);
    $member->syncRoles($memberRole);

    $this->withHeader('Authorization', 'Bearer '.logtoToken(JwtTestHelper::claims('logto-member'), $this->keys))
        ->postJson('/api/v1/members', [
            'logto_id' => 'logto-bob',
            'student_id' => 'STU-0004',
            'first_name' => 'Bob',
            'last_name' => 'Jones',
            'email' => 'bob@example.com',
        ])
        ->assertForbidden();
});
