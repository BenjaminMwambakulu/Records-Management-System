<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
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

        foreach (self::MODULE_PERMISSIONS as $modulePermissions) {
            foreach ($modulePermissions as $permission) {
                Permission::findOrCreate($permission, self::GUARD);
            }
        }

        foreach (self::ROLE_PERMISSIONS as $role => $permissions) {
            Role::findOrCreate($role, self::GUARD)
                ->syncPermissions($permissions);
        }

        Role::findOrCreate('superadmin', self::GUARD)
            ->syncPermissions(Permission::all());
    }
}
