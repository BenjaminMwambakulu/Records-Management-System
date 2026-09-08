<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

class RoleSyncService
{
    public function __construct(
        protected LogtoService $logtoService,
    ) {}

    /**
     * Resolve user roles based on priority rules:
     * - Superadmin: Local is Source of Truth → push to Logto
     * - Otherwise: Logto is Source of Truth → pull to local
     */
    public function syncUserAndRoles(User $user, array $incomingLogtoRoles = []): void
    {
        if ($user->hasRole('superadmin', 'logto')) {
            $this->pushLocalRolesToLogto($user);
        } else {
            $this->pullLogtoRolesToLocal($user, $incomingLogtoRoles);
        }
    }

    /**
     * Push local Spatie roles up to Logto (superadmin override).
     */
    public function pushLocalRolesToLogto(User $user): void
    {
        if (! $user->logto_id) {
            Log::warning('Cannot push roles to Logto: user has no logto_id', ['user_id' => $user->id]);

            return;
        }

        try {
            $localRoles = $user->getRoleNames()->toArray();
            $cacheKey = "logto:roles_synced:{$user->logto_id}";
            $previouslySynced = cache()->get($cacheKey);

            if ($previouslySynced !== null && $previouslySynced === $localRoles) {
                return;
            }

            $logtoRoleIds = $this->mapLocalRoleNamesToLogtoIds($localRoles);

            // Fetch current Logto roles
            $currentLogtoRoles = $this->logtoService->getUserRolesFromLogto($user->logto_id);
            $currentLogtoRoleIds = array_column($currentLogtoRoles, 'id');

            // Add missing roles
            foreach ($logtoRoleIds as $roleId) {
                if (! in_array($roleId, $currentLogtoRoleIds)) {
                    $this->logtoService->assignRoleInLogto($user->logto_id, $roleId);
                }
            }

            // Remove roles that are no longer local
            foreach ($currentLogtoRoleIds as $roleId) {
                if (! in_array($roleId, $logtoRoleIds)) {
                    $this->logtoService->removeRoleInLogto($user->logto_id, $roleId);
                }
            }

            cache()->put($cacheKey, $localRoles, now()->addMinutes(30));

            Log::info('Pushed local roles to Logto', [
                'user_id' => $user->id,
                'local_roles' => $localRoles,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to push local roles to Logto', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Assign local Spatie roles to a user, creating the roles if needed.
     *
     * @param  array<int, string>  $roleNames
     */
    public function assignLocalRoles(User $user, array $roleNames): void
    {
        $roles = array_map(
            fn (string $name) => Role::findOrCreate($name, 'logto'),
            array_values(array_unique(array_filter($roleNames, fn (string $name) => $name !== ''))),
        );

        $user->syncRoles($roles);
    }

    /**
     * Pull Logto roles to local Spatie (standard user sync).
     */
    protected function pullLogtoRolesToLocal(User $user, array $incomingLogtoRoles): void
    {
        $mappedRoleNames = array_values(array_unique($this->mapLogtoRolesToLocalNames($incomingLogtoRoles)));

        $this->assignLocalRoles($user, $mappedRoleNames);

        Log::info('Pulled Logto roles to local', [
            'user_id' => $user->id,
            'logto_roles' => $incomingLogtoRoles,
            'local_roles' => $mappedRoleNames,
        ]);
    }

    /**
     * Map local Spatie role names to Logto role IDs.
     * Override this method or config to customize the mapping.
     */
    protected function mapLocalRoleNamesToLogtoIds(array $localRoleNames): array
    {
        $mapping = config('services.logto.role_mapping', []);

        return array_map(fn ($name) => $mapping[$name] ?? $name, $localRoleNames);
    }

    /**
     * Map incoming Logto roles to local Spatie role names.
     * Logto roles arrive as objects/arrays with id + name; the priority is:
     * role_name_map (name alias) → role_mapping (id alias) → Logto name as-is.
     *
     * @param  array<int, array<string, mixed>|object>  $logtoRoles
     * @return array<int, string>
     */
    protected function mapLogtoRolesToLocalNames(array $logtoRoles): array
    {
        $nameMap = config('services.logto.role_name_map', []);
        $reverseMapping = array_flip(config('services.logto.role_mapping', []));

        return array_map(function (array|object $item) use ($nameMap, $reverseMapping): string {
            $role = (array) $item;
            $name = $role['name'] ?? null;
            $id = $role['id'] ?? null;

            if (is_string($name) && isset($nameMap[$name])) {
                return $nameMap[$name];
            }

            if (is_string($id) && isset($reverseMapping[$id])) {
                return $reverseMapping[$id];
            }

            return is_string($name) && $name !== '' ? $name : (is_string($id) && $id !== '' ? $id : '');
        }, $logtoRoles);
    }
}
