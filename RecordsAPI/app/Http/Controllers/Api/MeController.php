<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MemberResource;
use App\Http\Responses\APIResponse;
use App\Services\LogtoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MeController extends Controller
{
    use APIResponse;

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        /** @var array<string, mixed> $data */
        $data = (new MemberResource($user))->resolve();

        if ($user->logto_id) {
            $this->mergeLogtoProfile($data, app(LogtoService::class)->getUser($user->logto_id));
        }

        return $this->success(
            $data,
            'Current member retrieved successfully',
        );
    }

    public function permissions(Request $request): JsonResponse
    {
        $user = $request->user();

        /** @var list<string> $permissions */
        $permissions = $user->getAllPermissions()->pluck('name')->values()->all();

        return $this->success(
            ['permissions' => $permissions],
            'Permissions retrieved successfully',
        );
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'avatar' => 'sometimes|string|max:2048',
        ]);

        $user = $request->user();

        if (! $user->logto_id) {
            return $this->error('User not linked to Logto', 422);
        }

        $logtoData = [];
        if (isset($validated['name'])) {
            $logtoData['name'] = $validated['name'];
        }
        if (isset($validated['avatar'])) {
            $logtoData['avatar'] = $validated['avatar'];
        }

        if (empty($logtoData)) {
            return $this->error('No valid fields to update', 422);
        }

        app(LogtoService::class)->updateUserProfile($user->logto_id, $logtoData);

        return $this->success(
            $logtoData,
            'Profile updated successfully',
        );
    }

    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => 'required|file|image|max:5120',
        ]);

        $user = $request->user();

        if (! $user->logto_id) {
            return $this->error('User not linked to Logto', 422);
        }

        $file = $request->file('avatar');
        $filename = 'avatars/'.$user->logto_id.'/'.Str::uuid().'.'.$file->getClientOriginalExtension();

        Storage::disk('public')->put($filename, file_get_contents($file));

        $url = Storage::disk('public')->url($filename);

        try {
            app(LogtoService::class)->updateUserProfile($user->logto_id, [
                'avatar' => $url,
            ]);
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($filename);

            Log::warning('Avatar upload rolled back after Logto update failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $this->success(
            ['avatar' => $url],
            'Avatar uploaded successfully',
        );
    }

    /**
     * Fill gaps in the local member record from the full Logto profile.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $profile
     */
    protected function mergeLogtoProfile(array &$data, array $profile): void
    {
        if (empty($profile)) {
            return;
        }

        $parts = $this->nameParts($profile['name'] ?? '');

        $data['first_name'] = $data['first_name'] ?? ($parts[0] ?? null);
        $data['last_name'] = $data['last_name'] ?? ($parts[1] ?? null);

        $data['full_name'] = trim("{$data['first_name']} {$data['last_name']}");

        if ($data['full_name'] === '' && ! empty($profile['username'])) {
            $data['full_name'] = $profile['username'];
        }

        $data['email'] = $data['email'] ?? ($profile['primaryEmail'] ?? null);
        $data['avatar'] = $profile['avatar'] ?? null;
    }

    /**
     * @return array<int, string>
     */
    protected function nameParts(string $name): array
    {
        return array_values(array_filter(
            preg_split('/\s+/', trim($name)) ?: [],
            fn (string $part) => $part !== '' && $part !== 'undefined' && $part !== 'null',
        ));
    }
}
