<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Event;
use App\Models\FinancialRecord;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class PaymentService
{
    public function __construct(
        protected PayChanguService $paychangu,
    ) {}

    /**
     * Initiate a payment for a payable item
     */
    public function initiate(int $userId, string $payableType, int $payableId, float $amount, array $metadata = []): array
    {
        $payable = $this->getPayable($payableType, $payableId);

        if (! $payable) {
            throw new \RuntimeException('Payable item not found');
        }

        $user = \App\Models\User::findOrFail($userId);

        $payment = Payment::create([
            'payable_type' => $payableType,
            'payable_id' => $payableId,
            'user_id' => $userId,
            'amount' => $amount,
            'currency' => 'MWK',
            'status' => PaymentStatus::PENDING,
            'metadata' => array_merge($metadata, [
                'payable_title' => $this->getPayableTitle($payable),
            ]),
        ]);

        $checkoutData = $this->paychangu->initiate([
            'amount' => $amount,
            'first_name' => $user->name ?? $user->email,
            'email' => $user->email,
            'meta' => json_encode(['payment_id' => $payment->id]),
            'customization' => [
                'title' => 'Payment for '.$this->getPayableTitle($payable),
                'description' => 'Payment via PayChangu',
            ],
        ]);

        $payment->update([
            'tx_ref' => $checkoutData['tx_ref'],
            'status' => PaymentStatus::PROCESSING,
        ]);

        return [
            'payment' => $payment,
            'checkout_url' => $checkoutData['checkout_url'],
        ];
    }

    /**
     * Handle PayChangu callback
     */
    public function handleCallback(string $txRef): Payment
    {
        $payment = Payment::where('tx_ref', $txRef)->firstOrFail();

        try {
            $verification = $this->paychangu->verify($txRef);

            $payment->update([
                'status' => $verification['status'] === 'success'
                    ? PaymentStatus::COMPLETED
                    : PaymentStatus::FAILED,
                'provider_reference' => $verification['reference'] ?? null,
                'provider_response' => $verification,
                'payment_method' => $verification['authorization']['channel'] ?? null,
                'paid_at' => $verification['status'] === 'success' ? now() : null,
            ]);
        } catch (\Exception $e) {
            \Log::error('Payment verification failed', [
                'payment_id' => $payment->id,
                'tx_ref' => $txRef,
                'error' => $e->getMessage(),
            ]);

            $payment->update([
                'status' => PaymentStatus::FAILED,
                'provider_response' => ['error' => $e->getMessage()],
            ]);
        }

        if (! $payment->isCompleted()) {
            return $payment->refresh();
        }

        try {
            \App\Jobs\CreateFinancialRecordFromPayment::dispatch($payment);
            \App\Jobs\RegisterEventAttendanceFromPayment::dispatchSync($payment);
            \App\Jobs\PaymentCompletedJob::dispatch($payment);
        } catch (\Exception $e) {
            \Log::error('Payment side-effect dispatch failed', [
                'payment_id' => $payment->id,
                'tx_ref' => $txRef,
                'error' => $e->getMessage(),
            ]);
        }

        return $payment->refresh();
    }

    /**
     * List all payments (admin)
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        return Payment::query()
            ->with(['payable', 'user'])
            ->when($filters['status'] ?? null, function (Builder $query, string $status) {
                $query->where('status', $status);
            })
            ->when($filters['user_id'] ?? null, function (Builder $query, int $userId) {
                $query->where('user_id', $userId);
            })
            ->latest()
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * List user's payments
     */
    public function myPayments(int $userId, array $filters = []): LengthAwarePaginator
    {
        return Payment::query()
            ->with(['payable'])
            ->where('user_id', $userId)
            ->when($filters['status'] ?? null, function (Builder $query, string $status) {
                $query->where('status', $status);
            })
            ->latest()
            ->paginate($filters['per_page'] ?? 15);
    }

    public function find(int $id): ?Payment
    {
        return Payment::with(['payable', 'user'])->find($id);
    }

    private function getPayable(string $type, int $id)
    {
        return $type::find($id);
    }

    private function getPayableTitle($payable): string
    {
        return match (true) {
            $payable instanceof Event => $payable->title,
            default => class_basename($payable).' #'.$payable->id,
        };
    }

    private function createFinancialRecord(Payment $payment): void
    {
        $payable = $payment->payable;

        $categoryName = match (true) {
            $payable instanceof Event => 'Event Fees',
            default => 'Payments',
        };

        $category = \App\Models\FinancialCategory::where('name', $categoryName)->first();

        FinancialRecord::create([
            'title' => 'Payment for '.$this->getPayableTitle($payable),
            'type' => 'income',
            'amount' => $payment->amount,
            'category_id' => $category?->id,
            'transaction_date' => $payment->paid_at?->toDateString() ?? now()->toDateString(),
            'recorded_by' => $payment->user_id,
        ]);
    }

    /**
     * Auto-register user for event after successful payment
     */
    private function registerEventAttendance(Payment $payment): void
    {
        $payable = $payment->payable;

        if (! $payable instanceof Event) {
            return;
        }

        $existingRegistration = $payable->attendances()
            ->where('user_id', $payment->user_id)
            ->exists();

        if (! $existingRegistration) {
            $payable->attendances()->create([
                'user_id' => $payment->user_id,
            ]);
        }
    }

    /**
     * Initiate a mobile money payment for a payable item
     */
    public function initiateMobileMoney(int $userId, string $payableType, int $payableId, float $amount, string $phoneNumber, string $operatorRefId): array
    {
        $payable = $this->getPayable($payableType, $payableId);

        if (! $payable) {
            throw new \RuntimeException('Payable item not found');
        }

        $user = \App\Models\User::findOrFail($userId);
        $chargeId = (string) time().'-'.$userId;

        // Normalize phone number to 9 digits (strip country code and leading zero)
        $normalizedPhone = preg_replace('/^265/', '', ltrim($phoneNumber, '0'));
        $normalizedPhone = ltrim($normalizedPhone, '0');

        $payment = Payment::create([
            'payable_type' => $payableType,
            'payable_id' => $payableId,
            'user_id' => $userId,
            'amount' => $amount,
            'currency' => 'MWK',
            'status' => PaymentStatus::PENDING,
            'payment_carrier' => 'paychangu_mobile_money',
            'metadata' => [
                'payable_title' => $this->getPayableTitle($payable),
                'phone_number' => $phoneNumber,
                'operator_ref_id' => $operatorRefId,
                'charge_id' => $chargeId,
            ],
        ]);

        $result = $this->paychangu->initiateMobileMoney([
            'mobile' => $normalizedPhone,
            'mobile_money_operator_ref_id' => $operatorRefId,
            'amount' => $amount,
            'charge_id' => $chargeId,
            'email' => $user->email,
            'first_name' => $user->name ?? $user->email,
        ]);

        $payment->update([
            'tx_ref' => $result['ref_id'],
            'provider_reference' => $result['charge_id'],
            'status' => PaymentStatus::PROCESSING,
        ]);

        return [
            'payment' => $payment,
            'charge_id' => $result['charge_id'],
        ];
    }

    /**
     * Verify mobile money payment status
     */
    public function verifyMobileMoney(int $paymentId): Payment
    {
        $payment = Payment::findOrFail($paymentId);

        if (! $payment->metadata['charge_id'] ?? null) {
            throw new \RuntimeException('Invalid mobile money payment');
        }

        try {
            $verification = $this->paychangu->verifyMobileMoney($payment->metadata['charge_id']);

            $status = match ($verification['status']) {
                'success', 'successful' => PaymentStatus::COMPLETED,
                default => PaymentStatus::FAILED,
            };

            $payment->update([
                'status' => $status,
                'provider_response' => $verification['data'],
                'payment_method' => $verification['data']['authorization']['channel'] ?? null,
                'paid_at' => $status === PaymentStatus::COMPLETED ? now() : null,
            ]);
        } catch (\Exception $e) {
            \Log::error('Mobile money verification failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            $payment->update([
                'status' => PaymentStatus::FAILED,
                'provider_response' => ['error' => $e->getMessage()],
            ]);
        }

        if (! $payment->isCompleted()) {
            return $payment->refresh();
        }

        try {
            \App\Jobs\CreateFinancialRecordFromPayment::dispatch($payment);
            \App\Jobs\RegisterEventAttendanceFromPayment::dispatch($payment);
            \App\Jobs\PaymentCompletedJob::dispatch($payment);
        } catch (\Exception $e) {
            \Log::error('Payment side-effect dispatch failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $payment->refresh();
    }
}