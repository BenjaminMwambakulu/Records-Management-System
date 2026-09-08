<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PayChanguService
{
    private string $apiKey;
    private string $baseUrl;
    private string $callbackUrl;
    private string $returnUrl;

    public function __construct()
    {
        $this->apiKey = config('services.paychangu.secret_key');
        $this->baseUrl = config('services.paychangu.base_url', 'https://api.paychangu.com');
        $this->callbackUrl = config('services.paychangu.callback_url');
        $this->returnUrl = config('services.paychangu.return_url');
    }

    /**
     * Initiate a payment transaction
     *
     * @param array{amount: float, currency?: string, first_name?: string, last_name?: string, email?: string, meta?: string, customization?: array{title?: string, description?: string}} $data
     * @return array{checkout_url: string, tx_ref: string}
     */
    public function initiate(array $data): array
    {
        $txRef = $data['tx_ref'] ?? 'TX-'.$this->generateTxRef();

        $payload = [
            'amount' => number_format($data['amount'], 2, '.', ''),
            'currency' => $data['currency'] ?? 'MWK',
            'tx_ref' => $txRef,
            'callback_url' => $this->callbackUrl,
            'return_url' => $this->returnUrl,
        ];

        if (isset($data['first_name'])) {
            $payload['first_name'] = $data['first_name'];
        }

        if (isset($data['last_name'])) {
            $payload['last_name'] = $data['last_name'];
        }

        if (isset($data['email'])) {
            $payload['email'] = $data['email'];
        }

        if (isset($data['meta'])) {
            $payload['meta'] = $data['meta'];
        }

        if (isset($data['customization'])) {
            $payload['customization'] = $data['customization'];
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->apiKey,
            'Content-Type' => 'application/json',
        ])->post("{$this->baseUrl}/payment", $payload);

        $result = $response->json();

        if ($response->failed() || ($result['status'] ?? '') !== 'success') {
            throw new \RuntimeException($result['message'] ?? 'Payment initiation failed');
        }

        return [
            'checkout_url' => $result['data']['checkout_url'],
            'tx_ref' => $txRef,
        ];
    }

    /**
     * Verify transaction status
     *
     * @return array{status: string, tx_ref: string, amount: int, currency: string, authorization?: array, customer?: array, reference?: string}
     */
    public function verify(string $txRef): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->apiKey,
        ])->get("{$this->baseUrl}/verify-payment/{$txRef}");

        $result = $response->json();

        if ($response->failed() || ($result['status'] ?? '') !== 'success') {
            throw new \RuntimeException($result['message'] ?? 'Payment verification failed');
        }

        return $result['data'];
    }

    private function generateTxRef(): string
    {
        return strtoupper(Str::random(8)).'-'.time();
    }
}
