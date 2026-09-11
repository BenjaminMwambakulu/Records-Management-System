<?php

use App\Models\Event;
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

    Role::findOrCreate('member', 'web');
    Permission::findOrCreate('members.year_rep.manage', 'logto');

    $this->keys = JwtTestHelper::keyPair();

    Http::fake([
        'https://logto.test/oidc/jwks' => Http::response([
            'keys' => [JwtTestHelper::jwk($this->keys['public_pem'], $this->keys['kid'])],
        ]),
    ]);
});

test('unknown api route returns the standard error envelope', function () {
    $this->getJson('/api/v1/this-route-does-not-exist')
        ->assertStatus(404)
        ->assertJson([
            'success' => false,
            'message' => 'Resource not found',
            'data' => null,
            'errors' => null,
        ]);
});

test('missing model on api route returns the standard error envelope', function () {
    $this->post('/api/v1/payments/callback?tx_ref=DOES-NOT-EXIST')
        ->assertStatus(404)
        ->assertJson([
            'success' => false,
            'message' => 'Resource not found',
            'data' => null,
            'errors' => null,
        ]);
});

test('validation failures return the standard error envelope', function () {
    $user = User::factory()->create(['logto_id' => 'logto-validation-user']);
    $event = Event::factory()->create();

    $this->withHeader('Authorization', 'Bearer '.paymentToken(JwtTestHelper::claims('logto-validation-user'), $this->keys))
        ->postJson('/api/v1/payments', [
            'payable_type' => 'App\Models\Event',
            'payable_id' => $event->id,
        ])
        ->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'The given data was invalid.',
            'data' => null,
        ])
        ->assertJsonValidationErrors(['amount']);
});

test('abort in controller returns the standard error envelope', function () {
    $role = Role::findOrCreate('year_rep', 'logto');
    $role->givePermissionTo(Permission::findOrCreate('members.year_rep.manage', 'logto'));

    $user = User::withoutEvents(fn () => User::factory()->create([
        'logto_id' => 'logto-yearrep-abort',
        'academic_track' => null,
        'study_year' => null,
    ]))->syncRoles($role);

    $this->withHeader('Authorization', 'Bearer '.paymentToken(JwtTestHelper::claims('logto-yearrep-abort'), $this->keys))
        ->getJson('/api/v1/year-rep/students')
        ->assertStatus(403)
        ->assertJson([
            'success' => false,
            'message' => 'Your account is not configured with an academic track and study year.',
            'data' => null,
            'errors' => null,
        ]);
});

test('unhandled server errors return the standard envelope when debug is off', function () {
    config(['app.debug' => false]);

    User::factory()->create(['logto_id' => 'logto-500-user']);

    Http::fake([
        'https://logto.test/oidc/jwks' => Http::response([
            'keys' => [JwtTestHelper::jwk($this->keys['public_pem'], $this->keys['kid'])],
        ]),
        'https://logto.test/*' => Http::response('Service Unavailable', 503),
    ]);

    $this->withHeader('Authorization', 'Bearer '.paymentToken(JwtTestHelper::claims('logto-500-user'), $this->keys))
        ->patchJson('/api/v1/me', ['name' => 'Test'])
        ->assertStatus(500)
        ->assertJson([
            'success' => false,
            'message' => 'An unexpected error occurred.',
            'data' => null,
            'errors' => null,
        ]);
});