<?php

namespace App\Jobs;

use App\Mail\PaymentCompletedMail;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class PaymentCompletedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Payment $payment,
    ) {}

    public function handle(): void
    {
        Mail::to($this->payment->user->email)
            ->send(new PaymentCompletedMail($this->payment));

        activity('payment')
            ->performedOn($this->payment)
            ->causedBy($this->payment->user)
            ->withProperties([
                'amount' => $this->payment->amount,
                'currency' => $this->payment->currency,
            ])
            ->log('Payment completed');
    }
}
