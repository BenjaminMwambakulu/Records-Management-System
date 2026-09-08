<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\RoleSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogtoWebhookController extends Controller
{
    public function __construct(
        protected RoleSyncService $roleSyncService,
    ) {}

    public function handleWebhook(Request $request): JsonResponse
    {
        if (! $this->isValidSignature($request)) {
            Log::warning('Logto webhook signature validation failed');

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $payload = $request->all();
        $eventType = $payload['event'] ?? null;

        Log::info('Logto webhook received', ['event' => $eventType]);

        match ($eventType) {
            'User.Created', 'User.Data.Updated' => $this->handleUserSync($payload),
            'User.Roles.Updated' => $this->handleRoleUpdate($payload),
            default => Log::info('Unhandled Logto webhook event', ['event' => $eventType]),
        };

        return response()->json(['status' => 'ok']);
    }

    protected function handleUserSync(array $payload): void
    {
        $logtoUserId = $payload['data']['id'] ?? null;

        if (! $logtoUserId) {
            Log::warning('Logto webhook missing user ID', ['payload' => $payload]);

            return;
        }

        $userData = $payload['data'] ?? [];
        $roles = $userData['roles'] ?? [];

        $user = User::firstOrCreate(
            ['logto_id' => $logtoUserId],
            [
                'email' => $userData['primaryEmail'] ?? $userData['email'] ?? '',
                'first_name' => $this->extractFirstName($userData['name'] ?? ''),
                'last_name' => $this->extractLastName($userData['name'] ?? ''),
                'student_id' => $userData['username'] ?? $logtoUserId,
            ]
        );

        // Update attributes if user already existed
        $user->update([
            'email' => $userData['primaryEmail'] ?? $userData['email'] ?? $user->email,
            'first_name' => $this->extractFirstName($userData['name'] ?? $user->first_name.' '.$user->last_name),
            'last_name' => $this->extractLastName($userData['name'] ?? $user->first_name.' '.$user->last_name),
        ]);

        $this->roleSyncService->syncUserAndRoles($user, $roles);
    }

    protected function handleRoleUpdate(array $payload): void
    {
        $logtoUserId = $payload['data']['id'] ?? null;

        if (! $logtoUserId) {
            Log::warning('Logto webhook missing user ID for role update', ['payload' => $payload]);

            return;
        }

        $user = User::where('logto_id', $logtoUserId)->first();

        if (! $user) {
            Log::warning('Logto webhook: local user not found for role update', ['logto_id' => $logtoUserId]);

            return;
        }

        $roles = $payload['data']['roles'] ?? [];
        $this->roleSyncService->syncUserAndRoles($user, $roles);
    }

    protected function isValidSignature(Request $request): bool
    {
        $secret = config('services.logto.webhook_secret');

        if (empty($secret)) {
            Log::warning('Logto webhook secret not configured');

            return false;
        }

        $signature = $request->header('logto-signature-sha-256', $request->header('x-logto-signature'));

        if (! $signature) {
            return false;
        }

        $body = $request->getContent();
        $expectedHash = hash_hmac('sha256', $body, $secret);

        return hash_equals($expectedHash, $signature);
    }

    protected function extractFirstName(string $fullName): string
    {
        $parts = explode(' ', trim($fullName));

        return $parts[0] ?? '';
    }

    protected function extractLastName(string $fullName): string
    {
        $parts = explode(' ', trim($fullName));

        return end($parts) ?? '';
    }
}
