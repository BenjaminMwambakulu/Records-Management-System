<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\RoleSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    protected RoleSyncService $roleSyncService;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('member', 'web');
        $this->roleSyncService = app(RoleSyncService::class);
    }

    public function test_preserves_local_roles_when_incoming_logto_roles_are_empty(): void
    {
        $executive = Role::findOrCreate('executive', 'logto');
        $user = User::factory()->create();
        $user->assignRole($executive);

        $this->roleSyncService->syncUserAndRoles($user, []);

        $this->assertTrue($user->refresh()->getRoleNames()->contains('executive'));
    }

    public function test_still_syncs_roles_when_incoming_logto_roles_are_not_empty(): void
    {
        Role::findOrCreate('alumni', 'logto');
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('executive', 'logto'));

        $this->roleSyncService->syncUserAndRoles($user, [
            ['id' => 'role-alumni', 'name' => 'alumni'],
        ]);

        $roles = $user->refresh()->getRoleNames();
        $this->assertTrue($roles->contains('alumni'));
        $this->assertFalse($roles->contains('executive'));
    }
}