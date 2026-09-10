<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DefaultUsersSeeder extends Seeder
{
    private const string GUARD = 'logto';

    /**
     * @var array<int, array{env_key: string, role: string, label: string}>
     */
    private const array USERS = [
        [
            'env_key' => 'LOGTO_SUPER_ADMIN_USER',
            'role' => 'superadmin',
            'label' => 'Super Admin',
        ],
        [
            'env_key' => 'LOGTO_ADMIN_USER',
            'role' => 'admin',
            'label' => 'Admin',
        ],
    ];

    public function run(): void
    {
        foreach (self::USERS as $config) {
            $logtoId = env($config['env_key']);

            if (! is_string($logtoId) || $logtoId === '') {
                $this->command?->warn("Skipping {$config['label']}: {$config['env_key']} is not set in .env");

                continue;
            }

            $existing = User::where('logto_id', $logtoId)->first();

            if ($existing) {
                // Ensure role is assigned even if user already exists
                if (! $existing->hasRole($config['role'], self::GUARD)) {
                    $existing->syncRoles(Role::findOrCreate($config['role'], self::GUARD));
                    $this->command?->info("Assigned {$config['role']} role to existing user (logto_id: {$logtoId})");
                } else {
                    $this->command?->info("{$config['label']} already exists with correct role (logto_id: {$logtoId})");
                }

                continue;
            }

            // Create user without triggering the boot() auto-assign of 'member' role
            $user = User::withoutEvents(fn () => User::create([
                'logto_id' => $logtoId,
                'first_name' => $config['label'],
                'last_name' => '',
                'email' => "{$logtoId}@logto.placeholder",
                'student_id' => null,
            ]));

            $user->syncRoles(Role::findOrCreate($config['role'], self::GUARD));

            $this->command?->info("Created {$config['label']} user (logto_id: {$logtoId}) with {$config['role']} role");
        }
    }
}
