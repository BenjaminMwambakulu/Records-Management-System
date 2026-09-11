<?php

namespace App\Jobs;

use App\Enums\PaymentStatus;
use App\Models\Event;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RegisterEventAttendanceFromPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        public Payment $payment,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $this->payment->load('payable');

        if ($this->payment->status !== PaymentStatus::COMPLETED) {
            return;
        }

        $payable = $this->payment->payable;

        if (! $payable instanceof Event) {
            return;
        }

        $existingRegistration = $payable->attendances()
            ->where('user_id', $this->payment->user_id)
            ->exists();

        if (! $existingRegistration) {
            $payable->attendances()->create([
                'user_id' => $this->payment->user_id,
            ]);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::critical('Failed to register event attendance from payment', [
            'payment_id' => $this->payment->id,
            'error' => $e->getMessage(),
        ]);
    }
}
