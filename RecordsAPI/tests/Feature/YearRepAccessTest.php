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

    // User::booted() auto-assigns the 'member' role using the default (web)
    // guard, so it must exist before any user is created.
    Role::findOrCreate('member', 'web');

    $this->keys = JwtTestHelper::keyPair();

    Http::fake([
        'https://logto.test/oidc/jwks' => Http::response([
            'keys' => [JwtTestHelper::jwk($this->keys['public_pem'], $this->keys['kid'])],
        ]),
    ]);

    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
});

function yearRepAccessToken(array $claims, array $keys): string
{
    return JwtTestHelper::sign($claims, $keys['private_pem'], $keys['kid']);
}

function yearRepRoleUser(string $logtoId, string $roleName): User
{
    return User::withoutEvents(fn (): User => User::factory()->create(['logto_id' => $logtoId]))
        ->assignRole(Role::findOrCreate($roleName, 'logto'));
}

test('superadmin cannot access my students routes', function () {
    $superadmin = yearRepRoleUser('logto-superadmin', 'superadmin');

    expect($superadmin->hasPermissionTo('members.year_rep.manage', 'logto'))->toBeFalse();

    $this->withHeader('Authorization', 'Bearer '.yearRepAccessToken(JwtTestHelper::claims('logto-superadmin'), $this->keys))
        ->getJson('/api/v1/year-rep/students')
        ->assertForbidden();
});

test('users without the year-rep permission get 403 on my students routes', function () {
    yearRepRoleUser('logto-executive', 'executive');

    $this->withHeader('Authorization', 'Bearer '.yearRepAccessToken(JwtTestHelper::claims('logto-executive'), $this->keys))
        ->getJson('/api/v1/year-rep/students')
        ->assertForbidden();

    $this->withHeader('Authorization', 'Bearer '.yearRepAccessToken(JwtTestHelper::claims('logto-executive'), $this->keys))
        ->postJson('/api/v1/year-rep/students', ['first_name' => 'Test', 'last_name' => 'User'])
        ->assertForbidden();
});

test('members are denied the my students routes', function () {
    yearRepRoleUser('logto-member', 'member');

    $this->withHeader('Authorization', 'Bearer '.yearRepAccessToken(JwtTestHelper::claims('logto-member'), $this->keys))
        ->getJson('/api/v1/year-rep/students')
        ->assertForbidden();
});

test('year reps can access the my students routes', function () {
    $yearRep = yearRepRoleUser('logto-yearrep', 'year_rep');

    $response = $this->withHeader('Authorization', 'Bearer '.yearRepAccessToken(JwtTestHelper::claims('logto-yearrep'), $this->keys))
        ->getJson('/api/v1/year-rep/students');

    $response->assertOk();
});