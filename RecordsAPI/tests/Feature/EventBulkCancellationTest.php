<?php

use App\Enums\PaymentStatus;
use App\Jobs\SendRegistrationCancelledJob;
use App\Models\Event;
use App\Models\Payment;
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

    Queue::fake();
});

function eventBulkToken(array $claims, array $keys): string
{
    return JwtTestHelper::sign($claims, $keys['private_pem'], $keys['kid']);
}

function eventBulkRoleUser(string $logtoId, string $role): User
{
    $user = User::factory()->create(['logto_id' => $logtoId]);
    $user->syncRoles(Role::findOrCreate($role, 'logto'));

    return $user;
}

function eventBulkAuthHeader($test, string $logtoId): string
{
    return 'Bearer '.eventBulkToken(JwtTestHelper::claims($logtoId), $test->keys);
}

test('superadmin can cancel registrations for multiple members, refunds payments, and queues emails', function () {
    eventBulkRoleUser('logto-superadmin', 'superadmin');

    $event = Event::factory()->create(['event_date' => now()->addWeek()]);

    $memberA = eventBulkRoleUser('logto-a', 'member');
    $memberB = eventBulkRoleUser('logto-b', 'member');
    $memberC = eventBulkRoleUser('logto-c', 'member');

    $event->attendances()->createMany([
        ['user_id' => $memberA->id],
        ['user_id' => $memberB->id],
        ['user_id' => $memberC->id],
    ]);

    Payment::factory()->completed()->create([
        'user_id' => $memberA->id,
        'payable_type' => Event::class,
        'payable_id' => $event->id,
    ]);

    $this->withHeader('Authorization', eventBulkAuthHeader($this, 'logto-superadmin'))
        ->deleteJson("/api/v1/events/{$event->id}/attendances", [
            'user_ids' => [$memberA->id, $memberB->id],
            'reason' => 'Venue capacity reduced',
        ])
        ->assertOk()
        ->assertJsonPath('data.cancelled', 2);

    expect($event->attendances()->count())->toBe(1)
        ->and($event->attendances()->where('user_id', $memberA->id)->exists())->toBeFalse()
        ->and($event->attendances()->where('user_id', $memberB->id)->exists())->toBeFalse()
        ->and($event->attendances()->where('user_id', $memberC->id)->exists())->toBeTrue();

    expect(Payment::where('user_id', $memberA->id)->where('payable_id', $event->id)->first()->status)
        ->toBe(PaymentStatus::REFUNDED);

    Queue::assertPushed(SendRegistrationCancelledJob::class, 2);
});

test('cancelling without a reason still works', function () {
    eventBulkRoleUser('logto-superadmin', 'superadmin');

    $event = Event::factory()->create(['event_date' => now()->addWeek()]);

    $member = eventBulkRoleUser('logto-m', 'member');
    $event->attendances()->create(['user_id' => $member->id]);

    $this->withHeader('Authorization', eventBulkAuthHeader($this, 'logto-superadmin'))
        ->deleteJson("/api/v1/events/{$event->id}/attendances", ['user_ids' => [$member->id]])
        ->assertOk()
        ->assertJsonPath('data.cancelled', 1);

    expect($event->attendances()->where('user_id', $member->id)->exists())->toBeFalse();
});

test('only superadmins can cancel registrations', function () {
    $admin = eventBulkRoleUser('logto-admin', 'admin');

    $event = Event::factory()->create(['event_date' => now()->addWeek()]);

    $this->withHeader('Authorization', eventBulkAuthHeader($this, 'logto-admin'))
        ->deleteJson("/api/v1/events/{$event->id}/attendances", ['user_ids' => [$admin->id]])
        ->assertForbidden();
});

test('members cannot cancel registrations', function () {
    eventBulkRoleUser('logto-member', 'member');

    $event = Event::factory()->create(['event_date' => now()->addWeek()]);

    $this->withHeader('Authorization', eventBulkAuthHeader($this, 'logto-member'))
        ->deleteJson("/api/v1/events/{$event->id}/attendances", ['user_ids' => [1]])
        ->assertForbidden();
});

test('unauthenticated requests cannot cancel registrations', function () {
    $event = Event::factory()->create(['event_date' => now()->addWeek()]);

    $this->deleteJson("/api/v1/events/{$event->id}/attendances", ['user_ids' => [1]])
        ->assertStatus(401);
});

test('bulk cancellation requires at least one member id', function () {
    eventBulkRoleUser('logto-superadmin', 'superadmin');

    $event = Event::factory()->create(['event_date' => now()->addWeek()]);

    $this->withHeader('Authorization', eventBulkAuthHeader($this, 'logto-superadmin'))
        ->deleteJson("/api/v1/events/{$event->id}/attendances", ['user_ids' => []])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['user_ids']);
});

test('cancellation is refused for events that have already ended', function () {
    eventBulkRoleUser('logto-superadmin', 'superadmin');

    $event = Event::factory()->create(['event_date' => now()->subDay()]);

    $member = eventBulkRoleUser('logto-m', 'member');
    $event->attendances()->create(['user_id' => $member->id]);

    $this->withHeader('Authorization', eventBulkAuthHeader($this, 'logto-superadmin'))
        ->deleteJson("/api/v1/events/{$event->id}/attendances", ['user_ids' => [$member->id]])
        ->assertStatus(422);
});