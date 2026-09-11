<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RegistrationCancelledMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $member,
        public Event $event,
        public ?string $reason = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Registration cancelled - '.$this->event->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.registration-cancelled',
            with: [
                'name' => trim("{$this->member->first_name} {$this->member->last_name}"),
                'eventTitle' => $this->event->title,
                'reason' => $this->reason,
                'eventUrl' => config('app.frontend_url', '/').'/events/'.$this->event->id,
            ],
        );
    }
}