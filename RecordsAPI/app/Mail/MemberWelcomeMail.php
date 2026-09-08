<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class MemberWelcomeMail extends Mailable
{
    public function __construct(
        public User $member,
        public string $temporaryPassword,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to the CSIT Society',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.member-welcome',
            with: [
                'name' => trim("{$this->member->first_name} {$this->member->last_name}"),
                'email' => $this->member->email,
                'temporaryPassword' => $this->temporaryPassword,
                'appUrl' => config('app.url'),
            ],
        );
    }
}
