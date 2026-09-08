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

function roleAdminToken(array $keys, string $logtoId = 'logto-admin'): string
{
    return JwtTestHelper::sign(JwtTestHelper::claims($logtoId), $keys['private_pem'], $keys['kid']);
}

function createRoleAdminUser(): User
{
    $user = User::factory()->create(['logto_id' => 'logto-admin']);
    $role = Role::create(['name' => 'admin', 'guard_name' => 'logto']);
    $user->assignRole($role);

    return $user;
}

function createRoleSuperadminUser(): User
{
    $user = User::factory()->create(['logto_id' => 'logto-superadmin']);
    $role = Role::create(['name' => 'superadmin', 'guard_name' => 'logto']);
    $user->assignRole($role);

    return $user;
}

test('admin can list all roles', function () {
    createRoleAdminUser();
    Role::create(['name' => 'executive', 'guard_name' => 'logto']);
    Role::create(['name' => 'member', 'guard_name' => 'logto']);

    $this->withHeader('Authorization', 'Bearer '.roleAdminToken($this->keys))
        ->getJson('/api/v1/roles')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

test('non-admin cannot list roles', function () {
    $user = User::factory()->create(['logto_id' => 'logto-member']);
    $role = Role::create(['name' => 'member', 'guard_name' => 'logto']);
    $user->assignRole($role);

    $this->withHeader('Authorization', 'Bearer '.roleAdminToken($this->keys, 'logto-member'))
        ->getJson('/api/v1/roles')
        ->assertForbidden();
});

test('admin can create a role', function () {
    createRoleAdminUser();

    $this->withHeader('Authorization', 'Bearer '.roleAdminToken($this->keys))
        ->postJson('/api/v1/roles', ['name' => 'moderator'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'moderator');

    $this->assertDatabaseHas('roles', ['name' => 'moderator', 'guard_name' => 'logto']);
});

test('admin cannot create duplicate role', function () {
    createRoleAdminUser();
    Role::create(['name' => 'executive', 'guard_name' => 'logto']);

    $this->withHeader('Authorization', 'Bearer '.roleAdminToken($this->keys))
        ->postJson('/api/v1/roles', ['name' => 'executive'])
        ->assertUnprocessable();
});

test('admin can get role detail', function () {
    createRoleAdminUser();
    $role = Role::create(['name' => 'executive', 'guard_name' => 'logto']);
    $perm = Permission::create(['name' => 'events.view', 'guard_name' => 'logto']);
    $role->givePermissionTo($perm);

    $this->withHeader('Authorization', 'Bearer '.roleAdminToken($this->keys))
        ->getJson('/api/v1/roles/'.$role->id)
        ->assertOk()
        ->assertJsonPath('data.name', 'executive')
        ->assertJsonCount(1, 'data.permissions');
});

test('admin can update a role', function () {
    createRoleAdminUser();
    $role = Role::create(['name' => 'executive', 'guard_name' => 'logto']);

    $this->withHeader('Authorization', 'Bearer '.roleAdminToken($this->keys))
        ->putJson('/api/v1/roles/'.$role->id, ['name' => 'officer'])
        ->assertOk()
        ->assertJsonPath('data.name', 'officer');

    $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'officer']);
});

test('admin can delete a non-superadmin role', function () {
    createRoleAdminUser();
    $role = Role::create(['name' => 'executive', 'guard_name' => 'logto']);

    $this->withHeader('Authorization', 'Bearer '.roleAdminToken($this->keys))
        ->deleteJson('/api/v1/roles/'.$role->id)
        ->assertOk();

    $this->assertDatabaseMissing('roles', ['id' => $role->id]);
});

test('admin cannot delete superadmin role', function () {
    createRoleAdminUser();
    $role = Role::create(['name' => 'superadmin', 'guard_name' => 'logto']);

    $this->withHeader('Authorization', 'Bearer '.roleAdminToken($this->keys))
        ->deleteJson('/api/v1/roles/'.$role->id)
        ->assertForbidden();
});

test('admin can get permissions grouped by module', function () {
    createRoleAdminUser();
    Permission::create(['name' => 'members.view', 'guard_name' => 'logto']);
    Permission::create(['name' => 'members.create', 'guard_name' => 'logto']);
    Permission::create(['name' => 'events.view', 'guard_name' => 'logto']);

    $this->withHeader('Authorization', 'Bearer '.roleAdminToken($this->keys))
        ->getJson('/api/v1/permissions')
        ->assertOk()
        ->assertJsonPath('data.members.0', 'members.view')
        ->assertJsonPath('data.members.1', 'members.create')
        ->assertJsonPath('data.events.0', 'events.view');
});

test('admin can sync permissions for a role', function () {
    createRoleAdminUser();
    $role = Role::create(['name' => 'executive', 'guard_name' => 'logto']);
    Permission::create(['name' => 'events.view', 'guard_name' => 'logto']);
    Permission::create(['name' => 'events.create', 'guard_name' => 'logto']);
    Permission::create(['name' => 'members.view', 'guard_name' => 'logto']);

    $this->withHeader('Authorization', 'Bearer '.roleAdminToken($this->keys))
        ->putJson('/api/v1/roles/'.$role->id.'/permissions', [
            'permissions' => ['events.view', 'events.create'],
        ])
        ->assertOk()
        ->assertJsonPath('data.permissions_count', 2);

    $this->assertDatabaseHas('role_has_permissions', ['role_id' => $role->id]);
});

test('admin can assign role to user', function () {
    createRoleAdminUser();
    $role = Role::create(['name' => 'executive', 'guard_name' => 'logto']);
    $target = User::factory()->create(['logto_id' => 'logto-target']);

    $this->withHeader('Authorization', 'Bearer '.roleAdminToken($this->keys))
        ->postJson('/api/v1/roles/'.$role->id.'/users', ['user_id' => $target->id])
        ->assertOk();

    $this->assertTrue($target->fresh()->hasRole($role));
});

test('admin can remove role from user', function () {
    createRoleAdminUser();
    $role = Role::create(['name' => 'executive', 'guard_name' => 'logto']);
    $target = User::factory()->create(['logto_id' => 'logto-target']);
    $target->assignRole($role);

    $this->withHeader('Authorization', 'Bearer '.roleAdminToken($this->keys))
        ->deleteJson('/api/v1/roles/'.$role->id.'/users/'.$target->id)
        ->assertOk();

    $this->assertFalse($target->fresh()->hasRole($role));
});

test('admin cannot remove last superadmin', function () {
    $superadmin = createRoleSuperadminUser();
    $admin = createRoleAdminUser();
    $role = Role::where('name', 'superadmin')->where('guard_name', 'logto')->first();

    $this->withHeader('Authorization', 'Bearer '.roleAdminToken($this->keys))
        ->deleteJson('/api/v1/roles/'.$role->id.'/users/'.$superadmin->id)
        ->assertUnprocessable();
});
