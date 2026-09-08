<?php

namespace App\Mail;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentCompletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Payment $payment,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payment Confirmation - '.($this->payment->metadata['payable_title'] ?? 'Payment'),
        );
    }

    public function content(): Content
    {
        $event = $this->payment->payable;

        return new Content(
            html: 'emails.payment-completed',
            with: [
                'payment' => $this->payment,
                'payableTitle' => $this->payment->metadata['payable_title'] ?? 'Item',
                'qrCodeHash' => $event->qr_code_hash ?? null,
                'eventId' => $event->id ?? null,
            ],
        );
    }
}
