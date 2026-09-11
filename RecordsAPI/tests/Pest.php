<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\JwtTestHelper;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function paymentToken(array $claims, array $keys): string
{
    return JwtTestHelper::sign($claims, $keys['private_pem'], $keys['kid']);
}

function logtoAdminPermissionNames(): array
{
    return [
        'members.view',
        'members.create',
        'members.update',
        'members.delete',
        'members.export',
        'events.view',
        'events.create',
        'events.update',
        'events.delete',
        'events.checkin',
        'financials.view',
        'financials.create',
        'financials.update',
        'financials.delete',
        'financials.export',
        'assets.view',
        'assets.create',
        'assets.update',
        'assets.delete',
        'assets.checkout',
        'assets.return',
        'documents.view',
        'documents.create',
        'documents.update',
        'documents.delete',
        'documents.versions.create',
        'dashboard.view',
        'roles.manage',
        'activity_logs.view',
    ];
}

function logtoAdminRole(): Role
{
    foreach (logtoAdminPermissionNames() as $name) {
        Permission::findOrCreate($name, 'logto');
    }

    return Role::findOrCreate('admin', 'logto')->syncPermissions(logtoAdminPermissionNames());
}

function logtoSuperadminRole(): Role
{
    foreach (logtoAdminPermissionNames() as $name) {
        Permission::findOrCreate($name, 'logto');
    }

    return Role::findOrCreate('superadmin', 'logto')->syncPermissions(logtoAdminPermissionNames());
}

function logtoMemberRole(): Role
{
    foreach (['members.view', 'events.view', 'documents.view'] as $name) {
        Permission::findOrCreate($name, 'logto');
    }

    return Role::findOrCreate('member', 'logto')->syncPermissions(['members.view', 'events.view', 'documents.view']);
}
