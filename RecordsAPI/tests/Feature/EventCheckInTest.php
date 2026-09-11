<?php

use App\Enums\PaymentStatus;
use App\Models\Event;
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

    $this->keys = JwtTestHelper::keyPair();

    Http::fake([
        'https://logto.test/oidc/jwks' => Http::response([
            'keys' => [JwtTestHelper::jwk($this->keys['public_pem'], $this->keys['kid'])],
        ]),
    ]);
});

function checkInToken(array $claims, array $keys): string
{
    return JwtTestHelper::sign($claims, $keys['private_pem'], $keys['kid']);
}

function checkInRoleUser(string $logtoId, string $role): User
{
    $user = User::factory()->create(['logto_id' => $logtoId]);

    if ($role === 'superadmin') {
        $user->syncRoles(logtoSuperadminRole());
    } elseif ($role === 'member') {
        $user->syncRoles(logtoMemberRole());
    } else {
        $user->syncRoles(Role::findOrCreate($role, 'logto'));
    }

    return $user;
}

function checkInAuthHeader($test, string $logtoId): string
{
    return 'Bearer '.checkInToken(JwtTestHelper::claims($logtoId), $test->keys);
}

function checkInEvent(array $overrides = []): Event
{
    return Event::factory()->create(array_merge([
        'is_published' => true,
        'event_date' => now()->startOfDay(),
        'start_time' => null,
        'duration' => null,
    ], $overrides));
}

function checkInRegister(Event $event, User $member): void
{
    $event->attendances()->create(['user_id' => $member->id]);
}

function checkInTicketOf($test, Event $event, string $memberLogtoId): string
{
    return $test->withHeader('Authorization', checkInAuthHeader($test, $memberLogtoId))
        ->getJson("/api/v1/events/{$event->id}/ticket")
        ->assertOk()
        ->json('data.token');
}

test('a registered member receives a per-member check-in ticket', function () {
    $event = checkInEvent();
    $memberA = checkInRoleUser('logto-a', 'member');
    $memberB = checkInRoleUser('logto-b', 'member');
    checkInRegister($event, $memberA);
    checkInRegister($event, $memberB);

    $tokenA = checkInTicketOf($this, $event, 'logto-a');
    $tokenB = checkInTicketOf($this, $event, 'logto-b');

    expect($tokenA)->toBeString()
        ->and(substr_count($tokenA, '.'))->toBe(2)
        ->and($tokenA)->not->toBe($tokenB);
});

test('an unregistered user cannot receive a check-in ticket', function () {
    $event = checkInEvent();
    $member = checkInRoleUser('logto-unreg', 'member');

    $this->withHeader('Authorization', checkInAuthHeader($this, 'logto-unreg'))
        ->getJson("/api/v1/events/{$event->id}/ticket")
        ->assertStatus(403);
});

test('check-in tickets require authentication', function () {
    $event = checkInEvent();

    $this->getJson("/api/v1/events/{$event->id}/ticket")->assertStatus(401);
});

test('a checker can check in a registered attendee by scanning their token', function () {
    $superadmin = checkInRoleUser('logto-superadmin', 'superadmin');
    $event = checkInEvent();
    $member = checkInRoleUser('logto-scan', 'member');
    checkInRegister($event, $member);

    $token = checkInTicketOf($this, $event, 'logto-scan');

    $this->withHeader('Authorization', checkInAuthHeader($this, 'logto-superadmin'))
        ->postJson("/api/v1/events/{$event->id}/check-in", ['token' => $token])
        ->assertOk()
        ->assertJsonPath('data.status', 'checked_in')
        ->assertJsonPath('data.member.id', $member->id);

    $attendance = $event->attendances()->firstWhere('user_id', $member->id);
    expect($attendance->checked_in_at)->not->toBeNull()
        ->and($attendance->checked_in_by)->toBe($superadmin->id);
});

test('a checker can check in a registered attendee manually by member id', function () {
    checkInRoleUser('logto-superadmin', 'superadmin');
    $event = checkInEvent();
    $member = checkInRoleUser('logto-manual', 'member');
    checkInRegister($event, $member);

    $this->withHeader('Authorization', checkInAuthHeader($this, 'logto-superadmin'))
        ->postJson("/api/v1/events/{$event->id}/check-in", ['user_id' => $member->id])
        ->assertOk()
        ->assertJsonPath('data.status', 'checked_in');

    expect($event->attendances()->firstWhere('user_id', $member->id)->checked_in_at)->not->toBeNull();
});

test('checking in an already checked-in attendee is idempotent', function () {
    checkInRoleUser('logto-superadmin', 'superadmin');
    $event = checkInEvent();
    $member = checkInRoleUser('logto-twice', 'member');
    checkInRegister($event, $member);

    $headers = ['Authorization' => checkInAuthHeader($this, 'logto-superadmin')];
    $url = "/api/v1/events/{$event->id}/check-in";

    $this->withHeaders($headers)->postJson($url, ['user_id' => $member->id])->assertOk();
    $this->withHeaders($headers)->postJson($url, ['user_id' => $member->id])
        ->assertOk()
        ->assertJsonPath('data.status', 'already_checked_in');

    expect($event->attendances()->firstWhere('user_id', $member->id)->checked_in_at)->not->toBeNull();
});

test('check-in is refused for members who are not registered', function () {
    checkInRoleUser('logto-superadmin', 'superadmin');
    $event = checkInEvent();
    $member = checkInRoleUser('logto-ghost', 'member');

    $this->withHeader('Authorization', checkInAuthHeader($this, 'logto-superadmin'))
        ->postJson("/api/v1/events/{$event->id}/check-in", ['user_id' => $member->id])
        ->assertStatus(422);
});

test('check-in is refused before the event day', function () {
    checkInRoleUser('logto-superadmin', 'superadmin');
    $event = checkInEvent(['event_date' => now()->addDay()->startOfDay()]);
    $member = checkInRoleUser('logto-early', 'member');
    checkInRegister($event, $member);

    $this->withHeader('Authorization', checkInAuthHeader($this, 'logto-superadmin'))
        ->postJson("/api/v1/events/{$event->id}/check-in", ['user_id' => $member->id])
        ->assertStatus(422);
});

test('check-in is refused after the event has ended', function () {
    checkInRoleUser('logto-superadmin', 'superadmin');
    $event = checkInEvent(['event_date' => now()->subDay()->startOfDay()]);
    $member = checkInRoleUser('logto-late', 'member');
    checkInRegister($event, $member);

    $this->withHeader('Authorization', checkInAuthHeader($this, 'logto-superadmin'))
        ->postJson("/api/v1/events/{$event->id}/check-in", ['user_id' => $member->id])
        ->assertStatus(422);
});

test('members without the events.checkin permission cannot check others in', function () {
    $event = checkInEvent();
    $target = checkInRoleUser('logto-target', 'member');
    checkInRegister($event, $target);

    $this->withHeader('Authorization', checkInAuthHeader($this, 'logto-target'))
        ->postJson("/api/v1/events/{$event->id}/check-in", ['user_id' => $target->id])
        ->assertForbidden();
});

test('check-in tokens are rejected when tampered with', function () {
    checkInRoleUser('logto-superadmin', 'superadmin');
    $event = checkInEvent();
    checkInRoleUser('logto-victim', 'member');

    $tampered = '999999.'.'999999.'.'deadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeef';

    $this->withHeader('Authorization', checkInAuthHeader($this, 'logto-superadmin'))
        ->postJson("/api/v1/events/{$event->id}/check-in", ['token' => $tampered])
        ->assertStatus(422);
});

test('a check-in token for another event is rejected', function () {
    checkInRoleUser('logto-superadmin', 'superadmin');
    $event = checkInEvent();
    $otherEvent = checkInEvent();
    $member = checkInRoleUser('logto-cross', 'member');
    checkInRegister($event, $member);

    $token = checkInTicketOf($this, $event, 'logto-cross');

    $this->withHeader('Authorization', checkInAuthHeader($this, 'logto-superadmin'))
        ->postJson("/api/v1/events/{$otherEvent->id}/check-in", ['token' => $token])
        ->assertStatus(422);
});

test('check-in requires either a token or a member id', function () {
    checkInRoleUser('logto-superadmin', 'superadmin');
    $event = checkInEvent();

    $this->withHeader('Authorization', checkInAuthHeader($this, 'logto-superadmin'))
        ->postJson("/api/v1/events/{$event->id}/check-in", [])
        ->assertStatus(422);
});

test('check-in requests require authentication', function () {
    $event = checkInEvent();

    $this->postJson("/api/v1/events/{$event->id}/check-in", ['user_id' => 1])->assertStatus(401);
});

test('a newly registered attendee is not considered checked in until check-in', function () {
    checkInRoleUser('logto-superadmin', 'superadmin');
    $event = checkInEvent();
    $member = checkInRoleUser('logto-fresh', 'member');
    checkInRegister($event, $member);

    expect($event->attendances()->firstWhere('user_id', $member->id)->checked_in_at)->toBeNull();

    $this->withHeader('Authorization', checkInAuthHeader($this, 'logto-superadmin'))
        ->postJson("/api/v1/events/{$event->id}/check-in", ['user_id' => $member->id])
        ->assertJsonPath('data.status', 'checked_in');
});