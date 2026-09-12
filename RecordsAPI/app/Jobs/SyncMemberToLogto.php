<?php

namespace App\Jobs;

use App\Mail\MemberWelcomeMail;
use App\Models\User;
use App\Services\LogtoService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;

class SyncMemberToLogto implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /**
     * @param  array<int, string>  $roles
     */
    public function __construct(
        public User $member,
        public array $roles = [],
    ) {}

    public function handle(LogtoService $logtoService): void
    {
        if ($this->member->logto_id) {
            $this->syncExistingUser($logtoService);

            return;
        }

        $this->createAndWelcomeUser($logtoService);
    }

    protected function createAndWelcomeUser(LogtoService $logtoService): void
    {
        $temporaryPassword = Str::password(
            length: 14,
            letters: true,
            numbers: true,
            symbols: false,
        );

        $name = trim("{$this->member->first_name} {$this->member->last_name}");

        try {
            $data = [
                'primaryEmail' => $this->member->email,
                'name' => $name,
            ];

            if (($username = $logtoService->normalizeUsername((string) $this->member->student_id)) !== null) {
                $data['username'] = $username;
            }

            $created = $logtoService->createUser($data);

            $logtoUserId = $created['id'] ?? null;

            if (! is_string($logtoUserId) || $logtoUserId === '') {
                throw new RuntimeException('Logto createUser response did not include an id.');
            }

            $this->member->update(['logto_id' => $logtoUserId]);

            $logtoService->updatePassword($logtoUserId, $temporaryPassword);

            if ($this->roles !== []) {
                $logtoService->assignRoles($logtoUserId, $this->roles);
            }
        } catch (\Throwable $e) {
            Log::warning('Member created locally but Logto sync failed; welcome email was still sent', [
                'user_id' => $this->member->id,
                'error' => $e->getMessage(),
            ]);
        }

        Mail::to($this->member->email)->send(new MemberWelcomeMail($this->member, $temporaryPassword));

        Log::info('Member created and welcome email dispatched', [
            'user_id' => $this->member->id,
            'logto_id' => $this->member->logto_id,
            'roles' => $this->roles,
        ]);
    }

    protected function syncExistingUser(LogtoService $logtoService): void
    {
        $logtoService->syncUserToLogto($this->member);

        if ($this->roles !== []) {
            $logtoService->assignRoles($this->member->logto_id, $this->roles);
        }

        Log::info('Member profile resynced to Logto', [
            'user_id' => $this->member->id,
            'logto_id' => $this->member->logto_id,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::critical('Failed to sync member to Logto', [
            'user_id' => $this->member->id,
            'error' => $e->getMessage(),
        ]);
    }
}
