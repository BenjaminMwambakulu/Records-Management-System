<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    private const string GUARD = 'logto';

    /**
     * @var array<string, list<string>>
     */
    private const array MODULE_PERMISSIONS = [
        'members' => [
            'members.view',
            'members.create',
            'members.update',
            'members.delete',
            'members.export',
        ],
        'events' => [
            'events.view',
            'events.create',
            'events.update',
            'events.delete',
            'events.checkin',
        ],
        'financials' => [
            'financials.view',
            'financials.create',
            'financials.update',
            'financials.delete',
            'financials.export',
        ],
        'assets' => [
            'assets.view',
            'assets.create',
            'assets.update',
            'assets.delete',
            'assets.checkout',
            'assets.return',
        ],
        'documents' => [
            'documents.view',
            'documents.create',
            'documents.update',
            'documents.delete',
            'documents.versions.create',
        ],
        'dashboard' => [
            'dashboard.view',
        ],
        'system' => [
            'roles.manage',
            'activity_logs.view',
        ],
    ];

    /**
     * @var array<string, list<string>>
     */
    private const array ROLE_PERMISSIONS = [
        'executive' => [
            'members.view',
            'members.create',
            'members.update',
            'members.export',
            'events.view',
            'events.create',
            'events.update',
            'events.delete',
            'events.checkin',
            'financials.view',
            'financials.create',
            'financials.update',
            'financials.export',
            'assets.view',
            'assets.create',
            'assets.update',
            'assets.checkout',
            'assets.return',
            'documents.view',
            'documents.create',
            'documents.update',
            'documents.versions.create',
            'dashboard.view',
        ],
        'member' => [
            'members.view',
            'events.view',
            'documents.view',
        ],
        'alumni' => [
            'events.view',
            'documents.view',
        ],
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $now = now()->toDateTimeString();
        $rows = [];

        foreach (self::MODULE_PERMISSIONS as $modulePermissions) {
            foreach ($modulePermissions as $permission) {
                $rows[] = [
                    'name' => $permission,
                    'guard_name' => self::GUARD,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('permissions')->insertOrIgnore($rows);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $allPermissions = DB::table('permissions')
            ->where('guard_name', self::GUARD)
            ->pluck('name')
            ->toArray();

        foreach (self::ROLE_PERMISSIONS as $roleName => $permissionNames) {
            $role = Role::findOrCreate($roleName, self::GUARD);
            $role->syncPermissions(array_intersect($allPermissions, $permissionNames));
        }

        $superadmin = Role::findOrCreate('superadmin', self::GUARD);
        $superadmin->syncPermissions($allPermissions);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
