<?php

use App\Enums\PaymentStatus;
use App\Models\Event;
use App\Models\User;
use App\Services\PayChanguService;
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

function paymentToken(array $claims, array $keys): string
{
    return JwtTestHelper::sign($claims, $keys['private_pem'], $keys['kid']);
}

test('payment routes require authentication', function () {
    $this->postJson('/api/v1/payments', [])->assertStatus(401);
    $this->getJson('/api/v1/my-payments')->assertStatus(401);
});

test('authenticated user can initiate payment', function () {
    $user = User::factory()->create(['logto_id' => 'logto-payment-user']);
    $event = Event::factory()->create(['entry_fee' => 5000]);

    Http::fake([
        'https://api.paychangu.com/payment' => Http::response([
            'status' => 'success',
            'data' => [
                'checkout_url' => 'https://checkout.paychangu.com/test123',
                'data' => ['tx_ref' => 'TX-TEST123'],
            ],
        ]),
    ]);

    $this->withHeader('Authorization', 'Bearer '.paymentToken(JwtTestHelper::claims('logto-payment-user'), $this->keys))
        ->postJson('/api/v1/payments', [
            'payable_type' => 'App\Models\Event',
            'payable_id' => $event->id,
            'amount' => 5000,
        ])
        ->assertCreated()
        ->assertJsonPath('data.checkout_url', 'https://checkout.paychangu.com/test123')
        ->assertJsonPath('data.payment.status', 'processing');

    $this->assertDatabaseHas('payments', [
        'user_id' => $user->id,
        'payable_type' => 'App\Models\Event',
        'payable_id' => $event->id,
        'amount' => 5000,
        'status' => 'processing',
    ]);
});

test('initiate payment requires valid payable', function () {
    $user = User::factory()->create(['logto_id' => 'logto-payment-user']);

    $this->withHeader('Authorization', 'Bearer '.paymentToken(JwtTestHelper::claims('logto-payment-user'), $this->keys))
        ->postJson('/api/v1/payments', [
            'payable_type' => 'App\Models\Event',
            'payable_id' => 9999,
            'amount' => 5000,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['payable_id']);
});

test('initiate payment requires amount', function () {
    $user = User::factory()->create(['logto_id' => 'logto-payment-user']);
    $event = Event::factory()->create();

    $this->withHeader('Authorization', 'Bearer '.paymentToken(JwtTestHelper::claims('logto-payment-user'), $this->keys))
        ->postJson('/api/v1/payments', [
            'payable_type' => 'App\Models\Event',
            'payable_id' => $event->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['amount']);
});

test('user can view their payment', function () {
    $user = User::factory()->create(['logto_id' => 'logto-payment-user']);
    $event = Event::factory()->create();

    $payment = \App\Models\Payment::factory()->create([
        'user_id' => $user->id,
        'payable_type' => 'App\Models\Event',
        'payable_id' => $event->id,
    ]);

    $this->withHeader('Authorization', 'Bearer '.paymentToken(JwtTestHelper::claims('logto-payment-user'), $this->keys))
        ->getJson("/api/v1/payments/{$payment->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $payment->id);
});

test('user can list their payments', function () {
    $user = User::factory()->create(['logto_id' => 'logto-payment-user']);

    \App\Models\Payment::factory()->count(3)->create([
        'user_id' => $user->id,
    ]);

    $this->withHeader('Authorization', 'Bearer '.paymentToken(JwtTestHelper::claims('logto-payment-user'), $this->keys))
        ->getJson('/api/v1/my-payments')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});
