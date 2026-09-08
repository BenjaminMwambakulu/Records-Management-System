<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\LogtoService;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Events\RoleDeleted;
use Spatie\Permission\Events\RoleUpdated;

class SyncSuperadminToLogto
{
    public function __construct(
        protected LogtoService $logtoService,
    ) {}

    /**
     * Handle role update events — push superadmin changes to Logto.
     */
    public function handleRoleUpdated(RoleUpdated $event): void
    {
        $role = $event->role;

        // Find all users with this role and sync them
        $users = User::role($role->name)->get();

        foreach ($users as $user) {
            if ($user->hasRole('superadmin') && $user->logto_id) {
                $this->pushUserToLogto($user);
            }
        }
    }

    /**
     * Handle role deleted events — clean up in Logto if needed.
     */
    public function handleRoleDeleted(RoleDeleted $event): void
    {
        Log::info('Spatie role deleted', ['role' => $event->role->name]);
    }

    /**
     * Push user profile and roles to Logto.
     */
    protected function pushUserToLogto(User $user): void
    {
        try {
            $this->logtoService->syncUserToLogto($user);

            Log::info('Pushed superadmin user to Logto via event', [
                'user_id' => $user->id,
                'logto_id' => $user->logto_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to push superadmin to Logto via event', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
