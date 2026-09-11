<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('user creation auto-assigns the default member role on the logto guard', function () {
    Role::findOrCreate('member', 'logto');

    $user = User::factory()->create();

    expect($user->hasRole('member', 'logto'))->toBeTrue();
    expect($user->roles->where('guard_name', 'web')->count())->toBe(0);
});
