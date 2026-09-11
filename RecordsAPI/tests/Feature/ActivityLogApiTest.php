<?php

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\Support\JwtTestHelper;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.logto.endpoint' => 'https://logto.test',
        'services.logto.issuer' => 'https://logto.test/oidc',
        'services.logto.api_resource' => 'https://api.test',
        'activitylog.enabled' => false,
    ]);

    Cache::flush();

    $this->keys = JwtTestHelper::keyPair();

    Http::fake([
        'https://logto.test/oidc/jwks' => Http::response([
            'keys' => [JwtTestHelper::jwk($this->keys['public_pem'], $this->keys['kid'])],
        ]),
    ]);
});

function activityLogToken(array $claims, array $keys): string
{
    return JwtTestHelper::sign($claims, $keys['private_pem'], $keys['kid']);
}

test('activity logs require authentication', function () {
    $this->getJson('/api/v1/activity-logs')->assertStatus(401);
});

test('members cannot view activity logs', function () {
    Role::findOrCreate('member', 'logto');
    $member = User::factory()->create(['logto_id' => 'logto-member']);
    $member->syncRoles(Role::where('name', 'member')->where('guard_name', 'logto')->first());

    $this->withHeader('Authorization', 'Bearer '.activityLogToken(JwtTestHelper::claims('logto-member'), $this->keys))
        ->getJson('/api/v1/activity-logs')
        ->assertForbidden();
});

test('admins can list activity logs newest first', function () {
    $admin = User::factory()->create(['logto_id' => 'logto-admin']);
    $admin->syncRoles(logtoAdminRole());

    $older = Activity::create(['description' => 'created']);
    $newer = Activity::create(['description' => 'updated']);

    $older->forceFill(['created_at' => now()->subMinute()])->save();
    $newer->forceFill(['created_at' => now()])->save();

    $this->withHeader('Authorization', 'Bearer '.activityLogToken(JwtTestHelper::claims('logto-admin'), $this->keys))
        ->getJson('/api/v1/activity-logs')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.description', 'updated')
        ->assertJsonPath('data.1.description', 'created');
});

test('admins can filter logs by event', function () {
    $admin = User::factory()->create(['logto_id' => 'logto-admin']);
    $admin->syncRoles(logtoAdminRole());

    Activity::create(['description' => 'created a member', 'event' => 'created']);
    Activity::create(['description' => 'updated a member', 'event' => 'updated']);

    $this->withHeader('Authorization', 'Bearer '.activityLogToken(JwtTestHelper::claims('logto-admin'), $this->keys))
        ->getJson('/api/v1/activity-logs?event=updated')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.event', 'updated');
});

test('admins can search logs by description or actor name', function () {
    $admin = User::factory()->create(['logto_id' => 'logto-admin']);
    $admin->syncRoles(logtoAdminRole());

    Activity::create(['description' => 'created a document']);
    Activity::create(['description' => 'deleted an asset']);

    $this->withHeader('Authorization', 'Bearer '.activityLogToken(JwtTestHelper::claims('logto-admin'), $this->keys))
        ->getJson('/api/v1/activity-logs?search=document')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.description', 'created a document');
});

test('admins can filter logs by subject type', function () {
    $admin = User::factory()->create(['logto_id' => 'logto-admin']);
    $admin->syncRoles(logtoAdminRole());

    $event = Event::factory()->create();

    Activity::create([
        'description' => 'created event',
        'subject_type' => Event::class,
        'subject_id' => $event->id,
    ]);

    Activity::create([
        'description' => 'created something else',
        'subject_type' => User::class,
        'subject_id' => User::factory()->create()->id,
    ]);

    $this->withHeader('Authorization', 'Bearer '.activityLogToken(JwtTestHelper::claims('logto-admin'), $this->keys))
        ->getJson('/api/v1/activity-logs?subject_type='.urlencode(Event::class))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.subject.type', Event::class);
});

test('activity log entries include causer, subject and property changes', function () {
    $admin = User::factory()->create([
        'logto_id' => 'logto-admin',
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
    ]);
    $admin->syncRoles(logtoAdminRole());

    $causer = User::factory()->create(['first_name' => 'Grace', 'last_name' => 'Hopper']);
    $event = Event::factory()->create(['title' => 'Hackathon']);

    $activity = Activity::create([
        'description' => 'updated',
        'event' => 'updated',
        'subject_type' => Event::class,
        'subject_id' => $event->id,
        'causer_type' => User::class,
        'causer_id' => $causer->id,
        'attribute_changes' => [
            'attributes' => ['title' => 'Hackathon 2026'],
            'old' => ['title' => 'Hackathon'],
        ],
    ]);

    $this->withHeader('Authorization', 'Bearer '.activityLogToken(JwtTestHelper::claims('logto-admin'), $this->keys))
        ->getJson('/api/v1/activity-logs')
        ->assertOk()
        ->assertJsonPath('data.0.id', $activity->id)
        ->assertJsonPath('data.0.causer.full_name', 'Grace Hopper')
        ->assertJsonPath('data.0.subject.id', $event->id)
        ->assertJsonPath('data.0.subject.type', Event::class)
        ->assertJsonPath('data.0.subject.name', 'Hackathon')
        ->assertJsonPath('data.0.changes.attributes.title', 'Hackathon 2026')
        ->assertJsonPath('data.0.changes.old.title', 'Hackathon');
});
