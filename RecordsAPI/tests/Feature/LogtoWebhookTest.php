<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.logto.webhook_secret' => 'test-signing-key-123']);
});

function signedPayload(array $payload, ?string $secret = null): string
{
    $secret ??= config('services.logto.webhook_secret');

    return hash_hmac('sha256', json_encode($payload), $secret);
}

function webhookPayload(): array
{
    return [
        'hookId' => 'hook_test',
        'event' => 'User.Created',
        'createdAt' => '2026-09-04T22:00:00.000Z',
        'ip' => '203.0.113.10',
        'data' => [
            'id' => 'webhook-user-1',
            'primaryEmail' => 'webhook@example.com',
            'username' => 'Webhook-Man',
            'name' => 'Webhook Person',
        ],
    ];
}

test('POST /api/webhooks/logto accepts a valid Logto signature', function () {
    $payload = webhookPayload();

    $this->postJson('/api/webhooks/logto', $payload, [
        'logto-signature-sha-256' => signedPayload($payload),
    ])->assertOk()
        ->assertJsonPath('status', 'ok');

    expect(User::where('logto_id', 'webhook-user-1')->exists())->toBeTrue();
});

test('POST /api/webhooks/logto rejects a request without the signature header', function () {
    $this->postJson('/api/webhooks/logto', webhookPayload())
        ->assertStatus(401);
});

test('POST /api/webhooks/logto rejects a tampered body', function () {
    $payload = webhookPayload();
    $tampered = $payload;
    $tampered['data']['primaryEmail'] = 'changed@example.com';

    $this->postJson('/api/webhooks/logto', $payload, [
        'logto-signature-sha-256' => signedPayload($tampered),
    ])->assertStatus(401);
});
