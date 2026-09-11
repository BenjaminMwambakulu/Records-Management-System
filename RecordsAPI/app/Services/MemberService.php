<?php

namespace App\Services;

use App\Jobs\SyncMemberToLogto;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MemberService
{
    public function __construct(
        protected User $users,
        protected RoleSyncService $roleSyncService,
        protected LogtoService $logtoService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        return $this->users
            ->newQuery()
            ->with('roles')
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('first_name', 'ilike', "%{$search}%")
                        ->orWhere('last_name', 'ilike', "%{$search}%")
                        ->orWhere('student_id', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%");
                });
            })
            ->when($filters['role'] ?? null, function (Builder $query, string $role): void {
                $query->role($role);
            })
            ->latest()
            ->paginate($filters['per_page'] ?? 15);
    }

    public function find(int $id): ?User
    {
        return $this->users->find($id);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $roles
     */
    public function create(array $data, array $roles = []): User
    {
        $roles = $roles === [] ? ['member'] : $roles;

        $user = DB::transaction(function () use ($data, $roles): User {
            $user = $this->users->create($data);

            $this->roleSyncService->assignLocalRoles($user, $roles);

            return $user;
        });

        SyncMemberToLogto::dispatch($user, $roles);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): User
    {
        $user->update($data);

        $this->syncMemberDetailsToLogto($user);

        return $user;
    }

    public function delete(User $user): void
    {
        $user->delete();
    }

    /**
     * @param  array<int, string>  $roles
     */
    public function assignRoles(User $user, array $roles): User
    {
        $this->roleSyncService->assignLocalRoles($user, $roles);

        if ($user->logto_id) {
            $this->roleSyncService->pushLocalRolesToLogto($user);
        }

        return $user;
    }

    public function removeRole(User $user, string $role): User
    {
        $user->removeRole($role);

        if ($user->logto_id) {
            $this->roleSyncService->pushLocalRolesToLogto($user);
        }

        return $user;
    }

    protected function syncMemberDetailsToLogto(User $user): void
    {
        if (! $user->logto_id) {
            return;
        }

        try {
            $this->logtoService->syncUserToLogto($user);
        } catch (RuntimeException $e) {
            Log::warning('Member updated locally but Logto sync failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
