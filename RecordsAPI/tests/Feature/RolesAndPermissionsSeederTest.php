<?php

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('seeder creates all defined permissions on the logto guard', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $all = Permission::where('guard_name', 'logto')->pluck('name');

    expect($all)->toHaveCount(30);
    expect($all)
        ->toContain('members.view')
        ->toContain('members.create')
        ->toContain('members.update')
        ->toContain('members.delete')
        ->toContain('members.export')
        ->toContain('members.year_rep.manage')
        ->toContain('events.view')
        ->toContain('events.create')
        ->toContain('events.update')
        ->toContain('events.delete')
        ->toContain('events.checkin')
        ->toContain('financials.view')
        ->toContain('financials.create')
        ->toContain('financials.update')
        ->toContain('financials.delete')
        ->toContain('financials.export')
        ->toContain('assets.view')
        ->toContain('assets.create')
        ->toContain('assets.update')
        ->toContain('assets.delete')
        ->toContain('assets.checkout')
        ->toContain('assets.return')
        ->toContain('documents.view')
        ->toContain('documents.create')
        ->toContain('documents.update')
        ->toContain('documents.delete')
        ->toContain('documents.versions.create')
        ->toContain('dashboard.view')
        ->toContain('roles.manage')
        ->toContain('activity_logs.view');
});

test('executive role gets full operational access but no destructive asset or financial deletes', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $role = Role::where('name', 'executive')->where('guard_name', 'logto')->first();

    expect($role->permissions->pluck('name')->values()->all())
        ->toEqualCanonicalizing([
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
        ]);
});

test('member role gets view-only personal engagement permissions', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $role = Role::where('name', 'member')->where('guard_name', 'logto')->first();

    expect($role->permissions->pluck('name')->values()->all())
        ->toEqualCanonicalizing(['members.view', 'events.view', 'documents.view']);
});

test('alumni role gets read-only historic access', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $role = Role::where('name', 'alumni')->where('guard_name', 'logto')->first();

    expect($role->permissions->pluck('name')->values()->all())
        ->toEqualCanonicalizing(['events.view', 'documents.view']);
});

test('superadmin role gets every permission except year-rep student management', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $role = Role::where('name', 'superadmin')->where('guard_name', 'logto')->first();

    $expected = Permission::where('guard_name', 'logto')->pluck('name')
        ->reject(fn (string $name): bool => $name === 'members.year_rep.manage')
        ->values()->all();

    expect($role->permissions->pluck('name')->values()->all())
        ->toEqualCanonicalizing($expected);
});

test('admin role gets the same permission set as superadmin', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = Role::where('name', 'admin')->where('guard_name', 'logto')->first();
    $superadmin = Role::where('name', 'superadmin')->where('guard_name', 'logto')->first();

    expect($admin)->not->toBeNull();

    expect($admin->permissions->pluck('name')->values()->all())
        ->toEqualCanonicalizing($superadmin->permissions->pluck('name')->values()->all())
        ->not->toContain('members.year_rep.manage');
});

test('seeder is idempotent when run twice', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Role::count())->toBe(6);
    expect(Permission::count())->toBe(30);
    expect(Role::where('name', 'superadmin')->first()->permissions)->toHaveCount(29);
});
