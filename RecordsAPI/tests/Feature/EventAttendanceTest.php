<?php

use App\Models\Attendance;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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

function attendanceToken(array $claims, array $keys): string
{
    return JwtTestHelper::sign($claims, $keys['private_pem'], $keys['kid']);
}

test('event attendances require authentication', function () {
    $event = Event::factory()->create();

    $this->getJson("/api/v1/events/{$event->id}/attendances")->assertStatus(401);
});

test('any authenticated member can list event attendances', function () {
    User::factory()->create(['logto_id' => 'logto-attendanceviewer']);

    $event = Event::factory()->create();
    $member = User::factory()->create(['first_name' => 'Alice', 'last_name' => 'Wonder', 'student_id' => 'BIT-001-00']);

    Attendance::create([
        'event_id' => $event->id,
        'user_id' => $member->id,
        'checked_in_at' => '2026-09-01 09:30:00',
    ]);

    $this->withHeader('Authorization', 'Bearer '.attendanceToken(JwtTestHelper::claims('logto-attendanceviewer'), $this->keys))
        ->getJson("/api/v1/events/{$event->id}/attendances")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.user.id', $member->id)
        ->assertJsonPath('data.0.user.full_name', 'Alice Wonder')
        ->assertJsonPath('data.0.user.student_id', 'BIT-001-00')
        ->assertJsonPath('data.0.checked_in_at', '2026-09-01T09:30:00.000000Z');
});

test('event with no checked-in members returns an empty list', function () {
    User::factory()->create(['logto_id' => 'logto-attendanceviewer']);
    $event = Event::factory()->create();

    $this->withHeader('Authorization', 'Bearer '.attendanceToken(JwtTestHelper::claims('logto-attendanceviewer'), $this->keys))
        ->getJson("/api/v1/events/{$event->id}/attendances")
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('attendances are scoped to the requested event', function () {
    User::factory()->create(['logto_id' => 'logto-attendanceviewer']);

    $event = Event::factory()->create();
    $otherEvent = Event::factory()->create();

    Attendance::create([
        'event_id' => $otherEvent->id,
        'user_id' => User::factory()->create()->id,
        'checked_in_at' => now(),
    ]);

    $this->withHeader('Authorization', 'Bearer '.attendanceToken(JwtTestHelper::claims('logto-attendanceviewer'), $this->keys))
        ->getJson("/api/v1/events/{$event->id}/attendances")
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('event attendances respect per_page pagination limit', function () {
    User::factory()->create(['logto_id' => 'logto-attendanceviewer']);
    $event = Event::factory()->create();

    foreach (range(1, 5) as $i) {
        Attendance::create([
            'event_id' => $event->id,
            'user_id' => User::factory()->create()->id,
            'checked_in_at' => now(),
        ]);
    }

    $this->withHeader('Authorization', 'Bearer '.attendanceToken(JwtTestHelper::claims('logto-attendanceviewer'), $this->keys))
        ->getJson("/api/v1/events/{$event->id}/attendances?per_page=2")
        ->assertOk()
        ->assertJsonCount(2, 'data');

    expect(Attendance::where('event_id', $event->id)->count())->toBe(5);
});
