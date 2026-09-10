<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DefaultUsersSeeder extends Seeder
{
    private const string GUARD = 'logto';

    /**
     * @var array<int, array{env_key: string, role: string, label: string, academic_track?: string, study_year?: int}>
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
        [
            'env_key' => 'LOGTO_YEAR4REP_BIT',
            'role' => 'year_rep',
            'label' => 'BIT Year 4 Rep',
            'academic_track' => 'BIT',
            'study_year' => 4,
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

                // Backfill academic_track and study_year if missing
                $updates = [];
                if (isset($config['academic_track']) && $existing->academic_track === null) {
                    $updates['academic_track'] = $config['academic_track'];
                }
                if (isset($config['study_year']) && $existing->study_year === null) {
                    $updates['study_year'] = $config['study_year'];
                }
                if ($updates !== []) {
                    $existing->update($updates);
                    $this->command?->info("Backfilled fields for {$config['label']}: " . implode(', ', array_keys($updates)));
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
                'academic_track' => $config['academic_track'] ?? null,
                'study_year' => $config['study_year'] ?? null,
            ]));

            $user->syncRoles(Role::findOrCreate($config['role'], self::GUARD));

            $this->command?->info("Created {$config['label']} user (logto_id: {$logtoId}) with {$config['role']} role");
        }
    }
}
