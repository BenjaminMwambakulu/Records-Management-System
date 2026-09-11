<?php

namespace App\Jobs;

use App\Enums\PaymentStatus;
use App\Models\FinancialRecord;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreateFinancialRecordFromPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Payment $payment,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $this->payment->load('payable', 'user');

        if ($this->payment->status !== PaymentStatus::COMPLETED) {
            return;
        }

        $payable = $this->payment->payable;

        $categoryName = match (true) {
            $payable instanceof \App\Models\Event => 'Event Fees',
            default => 'Payments',
        };

        $category = \App\Models\FinancialCategory::where('name', $categoryName)->first();

        FinancialRecord::create([
            'title' => 'Payment for '.$this->getPayableTitle($payable),
            'type' => 'income',
            'amount' => $this->payment->amount,
            'category_id' => $category?->id,
            'transaction_date' => $this->payment->paid_at?->toDateString() ?? now()->toDateString(),
            'recorded_by' => $this->payment->user_id,
        ]);
    }

    private function getPayableTitle($payable): string
    {
        return match (true) {
            $payable instanceof \App\Models\Event => $payable->title,
            default => class_basename($payable).' #'.$payable->id,
        };
    }
}
