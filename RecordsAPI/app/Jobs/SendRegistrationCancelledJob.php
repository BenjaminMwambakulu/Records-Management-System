<?php

namespace App\Jobs;

use App\Mail\RegistrationCancelledMail;
use App\Models\Event;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendRegistrationCancelledJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        public User $member,
        public Event $event,
        public ?string $reason = null,
    ) {}

    public function handle(): void
    {
        Mail::to($this->member->email)
            ->send(new RegistrationCancelledMail($this->member, $this->event, $this->reason));
    }

    public function failed(\Throwable $e): void
    {
        Log::critical('Failed to send registration cancellation notification', [
            'member_id' => $this->member->id,
            'event_id' => $this->event->id,
            'error' => $e->getMessage(),
        ]);
    }
}