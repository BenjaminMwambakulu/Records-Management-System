<?php

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

test('debug initiate payment shows response body', function () {
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

    $response = $this->withHeader('Authorization', 'Bearer '.paymentToken(JwtTestHelper::claims('logto-payment-user'), $this->keys))
        ->postJson('/api/v1/payments', [
            'payable_type' => 'App\Models\Event',
            'payable_id' => $event->id,
            'amount' => 5000,
        ]);

    dump('STATUS: ' . $response->status());
    dump('BODY: ' . $response->content());

    $response->assertCreated();
});
