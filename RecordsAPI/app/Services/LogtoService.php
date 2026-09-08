<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class LogtoService
{
    protected string $endpoint;

    protected string $m2mAppId;

    protected string $m2mAppSecret;

    protected string $managementApiResource;

    public function __construct()
    {
        $this->endpoint = rtrim((string) config('services.logto.endpoint'), '/');
        $this->m2mAppId = config('services.logto.m2m_app_id');
        $this->m2mAppSecret = config('services.logto.m2m_app_secret');
        $this->managementApiResource = (string) config('services.logto.management_api_resource')
            ?? "{$this->endpoint}/api";
    }

    public function getAccessToken(): string
    {
        return Cache::remember('logto:management_token', now()->addMinutes(55), function () {
            $response = Http::asForm()
                ->timeout(10)
                ->post("{$this->endpoint}/oidc/token", [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->m2mAppId,
                    'client_secret' => $this->m2mAppSecret,
                    'resource' => $this->managementApiResource,
                    'scope' => 'all',
                ]);

            if ($response->failed()) {
                Log::error('Logto M2M token request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new RuntimeException('Failed to obtain Logto M2M access token: '.$response->body());
            }

            return (string) $response->json('access_token');
        });
    }

    public function createUser(array $data): array
    {
        return $this->callManagementApi('post', '/api/users', $data, 'Failed to create Logto user');
    }

    public function findUserByEmail(string $email): ?array
    {
        $search = urlencode('%'.$email.'%');

        try {
            $users = $this->callManagementApi(
                'get',
                "/api/users?search={$search}",
                [],
                'Failed to find Logto user'
            );
        } catch (\Throwable $e) {
            return null;
        }

        $needle = strtolower($email);

        foreach ((array) $users as $user) {
            if (strtolower((string) ($user['primaryEmail'] ?? '')) === $needle) {
                return $user;
            }
        }

        return null;
    }

    public function updatePassword(string $userId, string $password): array
    {
        return $this->callManagementApi(
            'patch',
            "/api/users/{$userId}/password",
            ['password' => $password],
            'Failed to update Logto user password'
        );
    }

    public function updateUserProfile(string $userId, array $data): array
    {
        return $this->callManagementApi(
            'patch',
            "/api/users/{$userId}",
            $data,
            'Failed to update Logto user profile'
        );
    }

    public function sendPasswordReset(string $userId): array
    {
        return $this->callManagementApi(
            'post',
            "/api/users/{$userId}/password/reset",
            [],
            'Failed to send Logto password reset'
        );
    }

    /**
     * Assign API resource roles to a user in Logto. Roles that do not exist
     * in Logto are skipped silently so local-only roles are never an error.
     *
     * @param  array<int, string>  $roleNames
     */
    public function assignRoles(string $userId, array $roleNames): array
    {
        $roleIds = $this->resolveRoleIds($roleNames);

        if ($roleIds === []) {
            return [];
        }

        return $this->callManagementApi(
            'post',
            "/api/users/{$userId}/roles",
            ['roleIds' => $roleIds],
            'Failed to assign Logto roles'
        );
    }

    public function assignRoleInLogto(string $logtoUserId, string $logtoRoleId): void
    {
        $this->callManagementApi(
            'post',
            "/api/users/{$logtoUserId}/roles",
            ['roleIds' => [$logtoRoleId]],
            "Failed to assign role {$logtoRoleId} to Logto user {$logtoUserId}"
        );
    }

    public function removeRoleInLogto(string $logtoUserId, string $logtoRoleId): void
    {
        $this->callManagementApi(
            'delete',
            "/api/users/{$logtoUserId}/roles/{$logtoRoleId}",
            [],
            "Failed to remove role {$logtoRoleId} from Logto user {$logtoUserId}"
        );
    }

    public function syncUserToLogto(User $user): array
    {
        if (! $user->logto_id) {
            throw new RuntimeException("Cannot sync user {$user->id} to Logto: no logto_id.");
        }

        return $this->callManagementApi(
            'put',
            "/api/users/{$user->logto_id}",
            [
                'primaryEmail' => $user->email,
                'name' => trim("{$user->first_name} {$user->last_name}"),
                'username' => $user->student_id,
            ],
            "Failed to sync user {$user->id} to Logto"
        );
    }

    public function getUser(string $logtoUserId): array
    {
        try {
            return Cache::remember("logto_user_{$logtoUserId}", now()->addMinutes(5), function () use ($logtoUserId) {
                try {
                    return $this->callManagementApi(
                        'get',
                        "/api/users/{$logtoUserId}",
                        [],
                        'Failed to fetch Logto user'
                    ) ?: [];
                } catch (\Throwable $e) {
                    Log::warning('Logto fetch user failed', [
                        'logto_user_id' => $logtoUserId,
                        'error' => $e->getMessage(),
                    ]);

                    return [];
                }
            });
        } catch (\Throwable $e) {
            Log::warning('Logto fetch user failed (unavailable)', [
                'logto_user_id' => $logtoUserId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function getUserRolesFromLogto(string $logtoUserId): array
    {
        $response = $this->callManagementApi(
            'get',
            "/api/users/{$logtoUserId}/roles",
            [],
            "Failed to fetch roles for Logto user {$logtoUserId}"
        );

        return $response['items'] ?? [];
    }

    /**
     * Map role names to Logto role IDs via the management API.
     *
     * @param  array<int, string>  $roleNames
     * @return list<string>
     */
    protected function resolveRoleIds(array $roleNames): array
    {
        $response = Cache::remember('logto:roles:list', now()->addMinutes(10), function () {
            return $this->callManagementApi('get', '/api/roles', [], 'Failed to list Logto roles');
        });

        $roles = $response['items'] ?? $response;

        $idsByName = collect($roles)->pluck('id', 'name')->all();

        $ids = [];

        foreach ($roleNames as $name) {
            if (isset($idsByName[$name])) {
                $ids[] = $idsByName[$name];
            }
        }

        return array_values(array_unique($ids));
    }

    protected function callManagementApi(string $method, string $path, array $data, string $errorMessage): array
    {
        $token = $this->getAccessToken();

        $url = rtrim($this->endpoint, '/') . $path;

        $response = Http::acceptJson()
            ->timeout(30)
            ->withToken($token)
            ->{$method}($url, $data);

        if ($response->failed()) {
            Log::error($errorMessage, [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException("{$errorMessage}: ".$response->body());
        }

        return $response->json() ?? [];
    }
}
