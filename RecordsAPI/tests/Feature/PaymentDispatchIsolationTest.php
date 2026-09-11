<?php

use App\Enums\PaymentStatus;
use App\Models\Event;
use App\Models\FinancialRecord;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Contracts\Bus\Dispatcher;
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
        'services.paychangu.secret_key' => 'test-secret-key',
        'services.paychangu.base_url' => 'https://api.paychangu.com',
        'services.paychangu.callback_url' => 'https://test.com/api/v1/payments/callback',
        'services.paychangu.return_url' => 'https://test.com/payment/status',
    ]);

    Cache::flush();
    Role::findOrCreate('member', 'web');

    $this->keys = JwtTestHelper::keyPair();
});

test('a queue failure after a successful callback does not mark the payment as failed', function () {
    $user = User::factory()->create(['logto_id' => 'logto-cb-user']);

    $payment = Payment::factory()->create([
        'user_id' => $user->id,
        'tx_ref' => 'TX-CALLBACK',
        'status' => PaymentStatus::PROCESSING,
        'payable_type' => Event::class,
        'payable_id' => Event::factory()->create()->id,
    ]);

    Http::fake([
        'https://api.paychangu.com/verify-payment/TX-CALLBACK' => Http::response([
            'status' => 'success',
            'data' => [
                'status' => 'success',
                'tx_ref' => 'TX-CALLBACK',
                'amount' => 5000,
                'currency' => 'MWK',
                'authorization' => ['channel' => 'Card'],
                'reference' => 'REF-1',
            ],
        ]),
    ]);

    app()->instance(Dispatcher::class, Mockery::mock(Dispatcher::class, function ($mock) {
        $mock->shouldReceive('dispatch')->andThrow(new \RuntimeException('Queue connection unavailable'));
    }));

    app(PaymentService::class)->handleCallback('TX-CALLBACK');

    expect($payment->fresh()->status)->toBe(PaymentStatus::COMPLETED);
    expect($payment->fresh()->isCompleted())->toBeTrue();
});

test('a queue failure during mobile money verification keeps the payment completed', function () {
    $user = User::factory()->create(['logto_id' => 'logto-momo-user']);

    $payment = Payment::factory()->create([
        'user_id' => $user->id,
        'status' => PaymentStatus::PROCESSING,
        'metadata' => ['charge_id' => 'charge-123'],
        'payable_type' => Event::class,
        'payable_id' => Event::factory()->create()->id,
    ]);

    Http::fake([
        'https://api.paychangu.com/mobile-money/payments/charge-123/verify' => Http::response([
            'status' => 'success',
            'data' => [
                'status' => 'success',
                'charge_id' => 'charge-123',
                'amount' => 5000,
                'currency' => 'MWK',
                'mobile' => '0888123456',
                'authorization' => ['channel' => 'Airtel Money'],
            ],
        ]),
    ]);

    app()->instance(Dispatcher::class, Mockery::mock(Dispatcher::class, function ($mock) {
        $mock->shouldReceive('dispatch')->andThrow(new \RuntimeException('Queue connection unavailable'));
    }));

    app(PaymentService::class)->verifyMobileMoney($payment->id);

    expect($payment->fresh()->status)->toBe(PaymentStatus::COMPLETED);
});

test('a failed verification marks the payment as failed', function () {
    $user = User::factory()->create(['logto_id' => 'logto-rejected-user']);

    $payment = Payment::factory()->create([
        'user_id' => $user->id,
        'tx_ref' => 'TX-REJECTED',
        'status' => PaymentStatus::PROCESSING,
    ]);

    Http::fake([
        'https://api.paychangu.com/verify-payment/TX-REJECTED' => Http::response([
            'status' => 'success',
            'data' => ['status' => 'failed', 'tx_ref' => 'TX-REJECTED'],
        ]),
    ]);

    Queue::fake();

    app(PaymentService::class)->handleCallback('TX-REJECTED');

    expect($payment->fresh()->isFailed())->toBeTrue();
    Queue::assertNothingPushed();
});

test('a successful callback creates a financial record and registers attendance', function () {
    $event = Event::factory()->create(['title' => 'Hackathon']);
    $user = User::factory()->create(['logto_id' => 'logto-cb-success-user']);

    $payment = Payment::factory()->create([
        'user_id' => $user->id,
        'tx_ref' => 'TX-SUCCESS',
        'status' => PaymentStatus::PROCESSING,
        'payable_type' => Event::class,
        'payable_id' => $event->id,
        'amount' => 5000,
    ]);

    Http::fake([
        'https://api.paychangu.com/verify-payment/TX-SUCCESS' => Http::response([
            'status' => 'success',
            'data' => [
                'status' => 'success',
                'tx_ref' => 'TX-SUCCESS',
                'amount' => 5000,
                'currency' => 'MWK',
                'authorization' => ['channel' => 'Card'],
                'reference' => 'REF-1',
            ],
        ]),
    ]);

    app(PaymentService::class)->handleCallback('TX-SUCCESS');

    expect(FinancialRecord::where('title', 'Payment for Hackathon')->exists())->toBeTrue();
    expect($event->attendances()->where('user_id', $user->id)->exists())->toBeTrue();
});