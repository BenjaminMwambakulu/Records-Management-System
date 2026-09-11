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
        ])->timeout(15)->connectTimeout(5)->post(rtrim($this->baseUrl, '/').'/payment', $payload);

        $result = $response->json();

        if ($response->failed() || ($result['status'] ?? '') !== 'success') {
            \Log::error('PayChangu initiate failed', [
                'status' => $response->status(),
                'response' => $result,
            ]);
            throw new \RuntimeException('Payment initiation failed. Please try again.');
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
        ])->timeout(15)->connectTimeout(5)->get(rtrim($this->baseUrl, '/')."/verify-payment/{$txRef}");

        $result = $response->json();

        if ($response->failed() || ($result['status'] ?? '') !== 'success') {
            \Log::error('PayChangu verify failed', [
                'tx_ref' => $txRef,
                'status' => $response->status(),
                'response' => $result,
            ]);
            throw new \RuntimeException('Payment verification failed. Please try again.');
        }

        return $result['data'];
    }

    private function generateTxRef(): string
    {
        return strtoupper(Str::random(8)).'-'.time();
    }

    /**
     * Initiate a mobile money payment
     *
     * @param array{mobile: string, mobile_money_operator_ref_id: string, amount: float|string, charge_id: string, email?: string, first_name?: string, last_name?: string} $data
     * @return array{charge_id: string, ref_id: string, trans_id: string|null, status: string}
     */
    public function initiateMobileMoney(array $data): array
    {
        $payload = [
            'mobile' => $data['mobile'],
            'mobile_money_operator_ref_id' => $data['mobile_money_operator_ref_id'],
            'amount' => number_format((float) $data['amount'], 2, '.', ''),
            'charge_id' => $data['charge_id'],
        ];

        if (isset($data['email'])) {
            $payload['email'] = $data['email'];
        }

        if (isset($data['first_name'])) {
            $payload['first_name'] = $data['first_name'];
        }

        if (isset($data['last_name'])) {
            $payload['last_name'] = $data['last_name'];
        }

        $url = rtrim($this->baseUrl, '/').'/mobile-money/payments/initialize';
        \Log::info('PayChangu mobile money request', ['url' => $url, 'payload' => $payload]);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->timeout(15)->connectTimeout(5)->post($url, $payload);

        $result = $response->json();
        \Log::info('PayChangu mobile money response', ['status' => $response->status(), 'result' => $result]);

        if ($response->failed() || ($result['status'] ?? '') !== 'success') {
            \Log::error('PayChangu mobile money initiate failed', [
                'status' => $response->status(),
                'response' => $result,
            ]);
            throw new \RuntimeException('Mobile money payment initiation failed. Please try again.');
        }

        return [
            'charge_id' => $result['data']['charge_id'],
            'ref_id' => $result['data']['ref_id'],
            'trans_id' => $result['data']['trans_id'] ?? null,
            'status' => $result['data']['status'],
        ];
    }

    /**
     * Verify mobile money payment status
     *
     * @return array{status: string, charge_id: string, ref_id: string, amount: int, currency: string, mobile: string, authorization?: array}
     */
    public function verifyMobileMoney(string $chargeId): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->apiKey,
            'Accept' => 'application/json',
        ])->timeout(15)->connectTimeout(5)->get(rtrim($this->baseUrl, '/')."/mobile-money/payments/{$chargeId}/verify");

        $result = $response->json();

        if ($response->failed()) {
            \Log::error('PayChangu mobile money verify failed', [
                'charge_id' => $chargeId,
                'status' => $response->status(),
                'response' => $result,
            ]);
            throw new \RuntimeException('Mobile money verification failed. Please try again.');
        }

        return [
            'status' => $result['status'] ?? 'failed',
            'data' => $result['data'] ?? null,
        ];
    }
}
