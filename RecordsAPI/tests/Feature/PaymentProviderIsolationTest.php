<?php

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
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
        'services.paychangu.secret_key' => 'test-secret-key',
        'services.paychangu.callback_url' => 'https://test.com/api/v1/payments/callback',
        'services.paychangu.return_url' => 'https://test.com/payment/status',
    ]);

    Cache::flush();

    Role::create(['name' => 'member', 'guard_name' => 'web']);

    $this->keys = JwtTestHelper::keyPair();

    Http::fake([
        'https://logto.test/oidc/jwks' => Http::response([
            'keys' => [JwtTestHelper::jwk($this->keys['public_pem'], $this->keys['kid'])],
        ]),
    ]);
});

test('provider connection failure while initiating returns sanitized 502', function () {
    $user = User::factory()->create(['logto_id' => 'logto-provider-user']);
    $event = Event::factory()->create();

    Http::fake([
        'https://api.paychangu.com/payment' => fn () => throw new ConnectionException('Could not resolve host: api.paychangu.com'),
    ]);

    $this->withHeader('Authorization', 'Bearer '.paymentToken(JwtTestHelper::claims('logto-provider-user'), $this->keys))
        ->postJson('/api/v1/payments', [
            'payable_type' => 'App\Models\Event',
            'payable_id' => $event->id,
            'amount' => 5000,
        ])
        ->assertStatus(502)
        ->assertJson([
            'success' => false,
            'message' => 'Payment provider is unavailable. Please try again later.',
        ]);
});

test('provider connection failure while initiating mobile money returns sanitized 502', function () {
    $user = User::factory()->create(['logto_id' => 'logto-provider-user']);
    $event = Event::factory()->create();

    Http::fake([
        'https://api.paychangu.com/mobile-money/payments/initialize' => fn () => throw new ConnectionException('Could not resolve host: api.paychangu.com'),
    ]);

    $this->withHeader('Authorization', 'Bearer '.paymentToken(JwtTestHelper::claims('logto-provider-user'), $this->keys))
        ->postJson('/api/v1/payments/mobile-money', [
            'payable_type' => 'App\Models\Event',
            'payable_id' => $event->id,
            'amount' => 5000,
            'phone_number' => '0888123456',
            'operator_ref_id' => '27494cb5-ba9e-437f-a114-4e7a7686bcca',
        ])
        ->assertStatus(502)
        ->assertJson([
            'success' => false,
            'message' => 'Payment provider is unavailable. Please try again later.',
        ]);
});

test('provider rejection while initiating mobile money returns sanitized 422 without leaking upstream message', function () {
    $user = User::factory()->create(['logto_id' => 'logto-provider-user']);
    $event = Event::factory()->create();

    Http::fake([
        'https://api.paychangu.com/mobile-money/payments/initialize' => Http::response([
            'status' => 'error',
            'message' => 'Insufficient balance on operator wallet',
        ], 402),
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.paymentToken(JwtTestHelper::claims('logto-provider-user'), $this->keys))
        ->postJson('/api/v1/payments/mobile-money', [
            'payable_type' => 'App\Models\Event',
            'payable_id' => $event->id,
            'amount' => 5000,
            'phone_number' => '0888123456',
            'operator_ref_id' => '27494cb5-ba9e-437f-a114-4e7a7686bcca',
        ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'Mobile money payment initiation failed. Please try again.',
        ]);

    expect($response->json('message'))->not->toContain('Insufficient balance');
});