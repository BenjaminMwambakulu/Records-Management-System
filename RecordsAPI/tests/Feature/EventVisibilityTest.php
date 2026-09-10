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

    // User::booted() auto-assigns the 'member' role using the default (web)
    // guard, so it must exist before any user is created.
    Role::findOrCreate('member', 'web');

    $this->keys = JwtTestHelper::keyPair();

    Http::fake([
        'https://logto.test/oidc/jwks' => Http::response([
            'keys' => [JwtTestHelper::jwk($this->keys['public_pem'], $this->keys['kid'])],
        ]),
    ]);
});

function eventVisibilityToken(array $claims, array $keys): string
{
    return JwtTestHelper::sign($claims, $keys['private_pem'], $keys['kid']);
}

function eventVisibilityAdminRole(): Role
{
    foreach (['events.view'] as $permission) {
        Permission::findOrCreate($permission, 'logto');
    }

    return Role::findOrCreate('admin', 'logto')->syncPermissions(['events.view']);
}

test('guests only see published events when listing', function () {
    $published = Event::factory()->create(['title' => 'Public Hackathon', 'is_published' => true]);
    Event::factory()->create(['title' => 'Draft Workshop', 'is_published' => false]);

    $this->getJson('/api/v1/events')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $published->id)
        ->assertJsonPath('data.0.is_published', true);

    // Even when a guest asks for unpublished events, the backend enforces published-only.
    $this->getJson('/api/v1/events?is_published=false')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $published->id);
});

test('guests cannot view an unpublished event', function () {
    $draft = Event::factory()->create(['is_published' => false]);
    $published = Event::factory()->create(['is_published' => true]);

    $this->getJson("/api/v1/events/{$draft->id}")->assertNotFound();

    $this->getJson("/api/v1/events/{$published->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $published->id);
});

test('privileged users see published and unpublished events when listing', function () {
    $admin = User::factory()->create(['logto_id' => 'logto-admin']);
    $admin->assignRole(eventVisibilityAdminRole());

    $published = Event::factory()->create(['title' => 'Public Hackathon', 'is_published' => true]);
    $draft = Event::factory()->create(['title' => 'Draft Workshop', 'is_published' => false]);

    $body = $this->withHeader('Authorization', 'Bearer '.eventVisibilityToken(JwtTestHelper::claims('logto-admin'), $this->keys))
        ->getJson('/api/v1/events')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->json();

    $ids = array_column($body['data'], 'id');
    expect($ids)->toContain($published->id)->toContain($draft->id);
});

test('year reps as board members can view unpublished events', function () {
    $yearRep = User::factory()->create(['logto_id' => 'logto-yearrep']);
    $yearRep->assignRole(Role::findOrCreate('year_rep', 'logto'));

    $draft = Event::factory()->create(['title' => 'Draft Workshop', 'is_published' => false]);

    $this->withHeader('Authorization', 'Bearer '.eventVisibilityToken(JwtTestHelper::claims('logto-yearrep'), $this->keys))
        ->getJson('/api/v1/events')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $draft->id);

    $this->withHeader('Authorization', 'Bearer '.eventVisibilityToken(JwtTestHelper::claims('logto-yearrep'), $this->keys))
        ->getJson("/api/v1/events/{$draft->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $draft->id);
});

test('privileged users can view an unpublished event', function () {
    $admin = User::factory()->create(['logto_id' => 'logto-admin']);
    $admin->assignRole(eventVisibilityAdminRole());

    $draft = Event::factory()->create(['is_published' => false]);

    $this->withHeader('Authorization', 'Bearer '.eventVisibilityToken(JwtTestHelper::claims('logto-admin'), $this->keys))
        ->getJson("/api/v1/events/{$draft->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $draft->id);
});