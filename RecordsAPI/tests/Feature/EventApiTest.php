<?php

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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

function eventToken(array $claims, array $keys): string
{
    return JwtTestHelper::sign($claims, $keys['private_pem'], $keys['kid']);
}

function eventAdminRole(): Role
{
    foreach (['members.create', 'events.create', 'events.update', 'events.delete', 'events.checkin'] as $permission) {
        Permission::findOrCreate($permission, 'logto');
    }

    return Role::findOrCreate('admin', 'logto')->syncPermissions([
        'members.create',
        'events.create',
        'events.update',
        'events.delete',
        'events.checkin',
    ]);
}

test('event routes require authentication', function () {
    $this->getJson('/api/v1/events')->assertStatus(401);
    $this->postJson('/api/v1/events', [])->assertStatus(401);
});

test('any authenticated member can list events', function () {
    User::factory()->create(['logto_id' => 'logto-eventviewer']);
    $older = Event::factory()->create(['title' => 'Older Event']);
    $newer = Event::factory()->create(['title' => 'Newer Event']);

    $older->forceFill(['created_at' => now()->subDay()])->save();
    $newer->forceFill(['created_at' => now()])->save();

    $this->withHeader('Authorization', 'Bearer '.eventToken(JwtTestHelper::claims('logto-eventviewer'), $this->keys))
        ->getJson('/api/v1/events')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $newer->id)
        ->assertJsonPath('data.1.id', $older->id)
        ->assertJsonPath('data.0.title', 'Newer Event');
});

test('any authenticated member can view an event', function () {
    $user = User::factory()->create(['logto_id' => 'logto-eventviewer']);
    $event = Event::factory()->create([
        'title' => 'Hackathon 2026',
        'event_date' => '2026-11-14 09:00:00',
        'is_published' => true,
        'created_by' => $user->id,
    ]);

    $this->withHeader('Authorization', 'Bearer '.eventToken(JwtTestHelper::claims('logto-eventviewer'), $this->keys))
        ->getJson("/api/v1/events/{$event->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $event->id)
        ->assertJsonPath('data.title', 'Hackathon 2026')
        ->assertJsonPath('data.created_by', $user->id)
        ->assertJsonPath('data.is_published', true)
        ->assertJsonPath('data.cover_url', null);
});

test('members cannot create, update, or delete events', function () {
    Role::create(['name' => 'member', 'guard_name' => 'logto']);
    $member = User::factory()->create(['logto_id' => 'logto-member']);
    $member->assignRole(Role::where('name', 'member')->where('guard_name', 'logto')->first());

    $event = Event::factory()->create();

    $token = 'Bearer '.eventToken(JwtTestHelper::claims('logto-member'), $this->keys);

    $this->withHeader('Authorization', $token)
        ->postJson('/api/v1/events', ['title' => 'Nope', 'event_date' => '2026-12-01 10:00:00'])
        ->assertForbidden();

    $this->withHeader('Authorization', $token)
        ->putJson("/api/v1/events/{$event->id}", ['title' => 'Nope'])
        ->assertForbidden();

    $this->withHeader('Authorization', $token)
        ->deleteJson("/api/v1/events/{$event->id}")
        ->assertForbidden();
});

test('admins can create an event', function () {
    $admin = User::factory()->create(['logto_id' => 'logto-admin']);
    $admin->assignRole(eventAdminRole());

    $this->withHeader('Authorization', 'Bearer '.eventToken(JwtTestHelper::claims('logto-admin'), $this->keys))
        ->postJson('/api/v1/events', [
            'title' => 'Tech Talk',
            'location' => 'Lecture Hall 3',
            'event_date' => '2026-10-05 14:00:00',
            'entry_fee' => 5000,
            'budget' => 100000,
            'is_published' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Tech Talk')
        ->assertJsonPath('data.created_by', $admin->id);

    $this->assertDatabaseHas('events', [
        'title' => 'Tech Talk',
        'location' => 'Lecture Hall 3',
        'is_published' => true,
        'created_by' => $admin->id,
    ]);
});

test('creating an event auto-generates the slug', function () {
    $admin = User::factory()->create(['logto_id' => 'logto-admin']);
    $admin->assignRole(eventAdminRole());

    $this->withHeader('Authorization', 'Bearer '.eventToken(JwtTestHelper::claims('logto-admin'), $this->keys))
        ->postJson('/api/v1/events', [
            'title' => 'Hackathon 2026',
            'event_date' => '2026-10-05 14:00:00',
            'entry_fee' => 3000,
            'budget' => 50000,
        ])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'hackathon-2026');

    $this->assertDatabaseHas('events', ['slug' => 'hackathon-2026']);
});

test('creating an event auto-generates a qr code hash', function () {
    $admin = User::factory()->create(['logto_id' => 'logto-admin']);
    $admin->assignRole(eventAdminRole());

    $this->withHeader('Authorization', 'Bearer '.eventToken(JwtTestHelper::claims('logto-admin'), $this->keys))
        ->postJson('/api/v1/events', [
            'title' => 'Orientation',
            'event_date' => '2026-10-05 14:00:00',
            'entry_fee' => 0,
            'budget' => 20000,
        ])
        ->assertCreated()
        ->assertJsonPath('data.qr_code_hash', fn (string $hash) => strlen($hash) === 32);

    $first = Event::where('title', 'Orientation')->first();

    $this->withHeader('Authorization', 'Bearer '.eventToken(JwtTestHelper::claims('logto-admin'), $this->keys))
        ->postJson('/api/v1/events', [
            'title' => 'Orientation Two',
            'event_date' => '2026-10-06 14:00:00',
            'entry_fee' => 0,
            'budget' => 20000,
        ]);

    $second = Event::where('title', 'Orientation Two')->first();

    expect($first->qr_code_hash)
        ->not->toBeNull()
        ->and($second->qr_code_hash)
        ->not->toBeNull()
        ->and($first->qr_code_hash)
        ->not->toBe($second->qr_code_hash);
});

test('creating an event requires a title and event date', function () {
    $admin = User::factory()->create(['logto_id' => 'logto-admin']);
    $admin->assignRole(eventAdminRole());

    $this->withHeader('Authorization', 'Bearer '.eventToken(JwtTestHelper::claims('logto-admin'), $this->keys))
        ->postJson('/api/v1/events', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['title', 'event_date']);
});

test('creating an event requires an entry fee and budget', function () {
    $admin = User::factory()->create(['logto_id' => 'logto-admin']);
    $admin->assignRole(eventAdminRole());

    $this->withHeader('Authorization', 'Bearer '.eventToken(JwtTestHelper::claims('logto-admin'), $this->keys))
        ->postJson('/api/v1/events', [
            'title' => 'Gala Night',
            'event_date' => '2026-10-05 14:00:00',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['entry_fee', 'budget']);
});

test('admins can set an entry fee and budget when creating an event', function () {
    $admin = User::factory()->create(['logto_id' => 'logto-admin']);
    $admin->assignRole(eventAdminRole());

    $this->withHeader('Authorization', 'Bearer '.eventToken(JwtTestHelper::claims('logto-admin'), $this->keys))
        ->postJson('/api/v1/events', [
            'title' => 'Gala Night',
            'event_date' => '2026-10-05 14:00:00',
            'entry_fee' => 10000.50,
            'budget' => 250000,
        ])
        ->assertCreated()
        ->assertJsonPath('data.entry_fee', '10000.50')
        ->assertJsonPath('data.budget', '250000.00');

    $this->assertDatabaseHas('events', [
        'title' => 'Gala Night',
        'entry_fee' => 10000.50,
        'budget' => 250000,
    ]);
});

test('admins can update an event entry fee and budget', function () {
    $admin = User::factory()->create(['logto_id' => 'logto-admin']);
    $admin->assignRole(eventAdminRole());

    $event = Event::factory()->create(['title' => 'Fundraiser']);

    $this->withHeader('Authorization', 'Bearer '.eventToken(JwtTestHelper::claims('logto-admin'), $this->keys))
        ->putJson("/api/v1/events/{$event->id}", [
            'entry_fee' => 5000,
            'budget' => 120000,
        ])
        ->assertOk()
        ->assertJsonPath('data.entry_fee', '5000.00')
        ->assertJsonPath('data.budget', '120000.00');

    expect($event->refresh()->entry_fee)->toBe('5000.00');
});

test('admins can update an event', function () {
    $admin = User::factory()->create(['logto_id' => 'logto-admin']);
    $admin->assignRole(eventAdminRole());

    $event = Event::factory()->create(['title' => 'Old Title']);

    $this->withHeader('Authorization', 'Bearer '.eventToken(JwtTestHelper::claims('logto-admin'), $this->keys))
        ->putJson("/api/v1/events/{$event->id}", [
            'title' => 'New Title',
            'is_published' => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.title', 'New Title')
        ->assertJsonPath('data.is_published', true);

    expect($event->refresh()->title)->toBe('New Title');
});

test('admins can delete an event', function () {
    $admin = User::factory()->create(['logto_id' => 'logto-admin']);
    $admin->assignRole(eventAdminRole());

    $event = Event::factory()->create();

    $this->withHeader('Authorization', 'Bearer '.eventToken(JwtTestHelper::claims('logto-admin'), $this->keys))
        ->deleteJson("/api/v1/events/{$event->id}")
        ->assertOk()
        ->assertJsonPath('message', 'Event deleted successfully');

    expect(Event::find($event->id))->toBeNull();
});

test('slug must be unique when explicitly provided', function () {
    $admin = User::factory()->create(['logto_id' => 'logto-admin']);
    $admin->assignRole(eventAdminRole());

    Event::factory()->create(['slug' => 'taken-slug']);

    $this->withHeader('Authorization', 'Bearer '.eventToken(JwtTestHelper::claims('logto-admin'), $this->keys))
        ->postJson('/api/v1/events', [
            'title' => 'Duplicate Slug',
            'slug' => 'taken-slug',
            'event_date' => '2026-10-05 14:00:00',
            'entry_fee' => 1000,
            'budget' => 30000,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['slug']);
});

test('members cannot upload or remove an event cover', function () {
    Role::create(['name' => 'member', 'guard_name' => 'logto']);
    $member = User::factory()->create(['logto_id' => 'logto-member']);
    $member->assignRole(Role::where('name', 'member')->where('guard_name', 'logto')->first());

    $event = Event::factory()->create();

    $token = 'Bearer '.eventToken(JwtTestHelper::claims('logto-member'), $this->keys);

    $this->withHeader('Authorization', $token)
        ->post("/api/v1/events/{$event->id}/cover", ['cover' => UploadedFile::fake()->image('cover.jpg')])
        ->assertForbidden();

    $this->withHeader('Authorization', $token)
        ->deleteJson("/api/v1/events/{$event->id}/cover")
        ->assertForbidden();
});

test('executive role can upload and remove an event cover', function () {
    Storage::fake('public');

    $executive = User::factory()->create(['logto_id' => 'logto-executive']);
    Permission::create(['name' => 'events.update', 'guard_name' => 'logto']);
    $executive->assignRole(
        Role::create(['name' => 'executive', 'guard_name' => 'logto'])
            ->givePermissionTo('events.update')
    );

    $event = Event::factory()->create();

    $token = 'Bearer '.eventToken(JwtTestHelper::claims('logto-executive'), $this->keys);

    $this->withHeader('Authorization', $token)
        ->post("/api/v1/events/{$event->id}/cover", ['cover' => UploadedFile::fake()->image('cover.jpg')])
        ->assertOk()
        ->assertJsonPath('message', 'Event cover uploaded successfully');

    expect($event->media()->where('collection_name', 'cover')->count())->toBe(1);

    $this->withHeader('Authorization', $token)
        ->deleteJson("/api/v1/events/{$event->id}/cover")
        ->assertOk()
        ->assertJsonPath('data.cover_url', null);

    expect($event->media()->where('collection_name', 'cover')->count())->toBe(0);
});

test('admins can upload an event cover', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['logto_id' => 'logto-admin']);
    $admin->assignRole(eventAdminRole());

    $event = Event::factory()->create();

    $this->withHeader('Authorization', 'Bearer '.eventToken(JwtTestHelper::claims('logto-admin'), $this->keys))
        ->post("/api/v1/events/{$event->id}/cover", ['cover' => UploadedFile::fake()->image('cover.jpg')])
        ->assertOk()
        ->assertJsonPath('message', 'Event cover uploaded successfully')
        ->assertJsonPath('data.cover_url', fn (string $url) => str_contains($url, '/storage/'));

    expect($event->media()->where('collection_name', 'cover')->count())->toBe(1);
});

test('cover upload requires an image file', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['logto_id' => 'logto-admin']);
    $admin->assignRole(eventAdminRole());

    $event = Event::factory()->create();

    $this->withHeader('Authorization', 'Bearer '.eventToken(JwtTestHelper::claims('logto-admin'), $this->keys))
        ->post("/api/v1/events/{$event->id}/cover", ['cover' => UploadedFile::fake()->create('cover.txt', 100)])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['cover']);
});

test('uploading a new cover replaces the previous one', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['logto_id' => 'logto-admin']);
    $admin->assignRole(eventAdminRole());

    $event = Event::factory()->create();
    $event->addMedia(UploadedFile::fake()->image('old.jpg'))->toMediaCollection('cover');

    $this->withHeader('Authorization', 'Bearer '.eventToken(JwtTestHelper::claims('logto-admin'), $this->keys))
        ->post("/api/v1/events/{$event->id}/cover", ['cover' => UploadedFile::fake()->image('new.jpg')])
        ->assertOk();

    expect($event->media()->where('collection_name', 'cover')->count())->toBe(1);
});

test('admins can remove an event cover', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['logto_id' => 'logto-admin']);
    $admin->assignRole(eventAdminRole());

    $event = Event::factory()->create();
    $event->addMedia(UploadedFile::fake()->image('cover.jpg'))->toMediaCollection('cover');

    $this->withHeader('Authorization', 'Bearer '.eventToken(JwtTestHelper::claims('logto-admin'), $this->keys))
        ->deleteJson("/api/v1/events/{$event->id}/cover")
        ->assertOk()
        ->assertJsonPath('data.cover_url', null);

    expect($event->media()->where('collection_name', 'cover')->count())->toBe(0);
});
