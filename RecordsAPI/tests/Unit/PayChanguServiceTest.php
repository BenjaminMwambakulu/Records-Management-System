<?php

use App\Services\PayChanguService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PayChanguServiceTest extends TestCase
{
    protected PayChanguService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.paychangu.secret_key' => 'test-secret-key',
            'services.paychangu.base_url' => 'https://api.paychangu.com',
            'services.paychangu.callback_url' => 'https://test.com/callback',
            'services.paychangu.return_url' => 'https://test.com/return',
        ]);

        $this->service = new PayChanguService();
    }

    public function test_initiate_returns_checkout_url(): void
    {
        Http::fake([
            'https://api.paychangu.com/payment' => Http::response([
                'status' => 'success',
                'data' => [
                    'checkout_url' => 'https://checkout.paychangu.com/test123',
                    'data' => ['tx_ref' => 'TX-TEST123'],
                ],
            ]),
        ]);

        $result = $this->service->initiate([
            'amount' => 5000,
            'first_name' => 'John',
            'email' => 'john@example.com',
        ]);

        $this->assertArrayHasKey('checkout_url', $result);
        $this->assertArrayHasKey('tx_ref', $result);
        $this->assertEquals('https://checkout.paychangu.com/test123', $result['checkout_url']);
    }

    public function test_initiate_throws_on_failure(): void
    {
        Http::fake([
            'https://api.paychangu.com/payment' => Http::response([
                'status' => 'failed',
                'message' => 'Invalid amount',
            ], 400),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Payment initiation failed. Please try again.');

        $this->service->initiate(['amount' => 0]);
    }

    public function test_verify_returns_payment_data(): void
    {
        Http::fake([
            'https://api.paychangu.com/verify-payment/TX-TEST123' => Http::response([
                'status' => 'success',
                'data' => [
                    'status' => 'success',
                    'tx_ref' => 'TX-TEST123',
                    'amount' => 5000,
                    'currency' => 'MWK',
                    'authorization' => ['channel' => 'Card'],
                ],
            ]),
        ]);

        $result = $this->service->verify('TX-TEST123');

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('TX-TEST123', $result['tx_ref']);
        $this->assertEquals('Card', $result['authorization']['channel']);
    }

    public function test_verify_throws_on_failure(): void
    {
        Http::fake([
            'https://api.paychangu.com/verify-payment/TX-FAILED' => Http::response([
                'status' => 'failed',
                'message' => 'Transaction not found',
            ], 404),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Payment verification failed. Please try again.');

        $this->service->verify('TX-FAILED');
    }
}
