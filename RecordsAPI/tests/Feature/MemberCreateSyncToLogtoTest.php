<?php

use App\Jobs\SyncMemberToLogto;
use App\Mail\MemberWelcomeMail;
use App\Models\User;
use App\Services\LogtoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Tests\Support\JwtTestHelper;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.logto.endpoint' => 'https://logto.test',
        'services.logto.issuer' => 'https://logto.test/oidc',
        'services.logto.api_resource' => 'https://api.test',
        'services.logto.m2m_app_id' => 'test-m2m',
        'services.logto.m2m_app_secret' => 'test-secret',
        'services.logto.management_api_resource' => 'https://api.test',
    ]);

    Cache::flush();

    $this->keys = JwtTestHelper::keyPair();

    Http::fake([
        'https://logto.test/oidc/jwks' => Http::response([
            'keys' => [JwtTestHelper::jwk($this->keys['public_pem'], $this->keys['kid'])],
        ]),
    ]);
});

function memberSyncToken(array $claims, array $keys): string
{
    return JwtTestHelper::sign($claims, $keys['private_pem'], $keys['kid']);
}

test('creating a member does not require logto_id and dispatches a sync job', function () {
    logtoAdminRole();
    $admin = User::factory()->create(['logto_id' => 'logto-admin']);
    $admin->syncRoles(Role::where('name', 'admin')->where('guard_name', 'logto')->first());

    Queue::fake();

    $this->withHeader('Authorization', 'Bearer '.memberSyncToken(JwtTestHelper::claims('logto-admin'), $this->keys))
        ->postJson('/api/v1/members', [
            'student_id' => 'BIT-001-22',
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'email' => 'alice.smith@must.ac.mw',
            'academic_track' => 'BIT',
            'enrolled_year' => 2024,
            'study_year' => 2,
            'skills' => ['PHP', 'Laravel'],
            'roles' => ['admin'],
        ])
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.logto_id', null)
        ->assertJsonPath('data.roles.0', 'admin');

    $member = User::where('email', 'alice.smith@must.ac.mw')->first();

    expect($member)->not->toBeNull()
        ->and($member->logto_id)->toBeNull()
        ->and($member->getRoleNames())->toContain('admin');

    Queue::assertPushed(SyncMemberToLogto::class, function (SyncMemberToLogto $job) use ($member) {
        return $job->member->is($member) && $job->roles === ['admin'];
    });
});

test('new members are assigned the member role by default', function () {
    logtoAdminRole();
    $admin = User::factory()->create(['logto_id' => 'logto-admin']);
    $admin->syncRoles(Role::where('name', 'admin')->where('guard_name', 'logto')->first());

    Queue::fake();

    $this->withHeader('Authorization', 'Bearer '.memberSyncToken(JwtTestHelper::claims('logto-admin'), $this->keys))
        ->postJson('/api/v1/members', [
            'student_id' => 'BIT-005-22',
            'first_name' => 'Carol',
            'last_name' => 'Danvers',
            'email' => 'carol.danvers@must.ac.mw',
        ])
        ->assertCreated()
        ->assertJsonPath('data.roles', ['member']);

    $member = User::where('email', 'carol.danvers@must.ac.mw')->first();

    expect($member)->not->toBeNull()
        ->and($member->getRoleNames())->toContain('member');

    Queue::assertPushed(SyncMemberToLogto::class, fn (SyncMemberToLogto $job) => $job->roles === ['member']);
});

test('non-admins cannot create members', function () {
    Role::findOrCreate('member', 'logto');
    $member = User::factory()->create(['logto_id' => 'logto-member']);
    $member->syncRoles(Role::where('name', 'member')->where('guard_name', 'logto')->first());

    $this->withHeader('Authorization', 'Bearer '.memberSyncToken(JwtTestHelper::claims('logto-member'), $this->keys))
        ->postJson('/api/v1/members', [
            'student_id' => 'BIT-002-22',
            'first_name' => 'Bob',
            'last_name' => 'Jones',
            'email' => 'bob.jones@must.ac.mw',
        ])
        ->assertForbidden();
});

test('the sync job creates the user in Logto, stores the id, assigns roles, and emails the temporary password', function () {
    $member = User::factory()->create([
        'email' => 'alice.smith@must.ac.mw',
        'first_name' => 'Alice',
        'last_name' => 'Smith',
        'student_id' => 'BIT-001-22',
        'logto_id' => null,
    ]);

    Http::fake([
        'https://logto.test/oidc/token' => Http::response(['access_token' => 'm2m-token'], 200),
        'https://logto.test/api/users' => Http::response([
            'id' => 'logto-new-id',
            'primaryEmail' => 'alice.smith@must.ac.mw',
            'name' => 'Alice Smith',
            'username' => 'BIT-001-22',
        ], 200),
        'https://logto.test/api/roles*' => Http::response([
            'items' => [['id' => 'role-admin', 'name' => 'admin']],
            'totalCount' => 1,
        ], 200),
        'https://logto.test/api/users/*' => Http::response([], 200),
    ]);

    Mail::fake();

    (new SyncMemberToLogto($member, ['admin']))->handle(app(LogtoService::class));

    expect($member->refresh()->logto_id)->toBe('logto-new-id');

    Http::assertSent(fn ($request) => $request->url() === 'https://logto.test/api/users'
        && $request->method() === 'POST');
    Http::assertSent(fn ($request) => str_contains($request->url(), '/api/users/logto-new-id/roles')
        && $request->method() === 'POST');

    Mail::assertSent(MemberWelcomeMail::class, function (MemberWelcomeMail $mail) use ($member) {
        return $mail->member->is($member)
            && strlen($mail->temporaryPassword) >= 14
            && $mail->hasTo($member->email);
    });
});

test('the sync job sends a Logto-safe username when the student id contains hyphens', function () {
    $member = User::factory()->create([
        'email' => 'alice.smith@must.ac.mw',
        'first_name' => 'Alice',
        'last_name' => 'Smith',
        'student_id' => 'BIT-023-22',
        'logto_id' => null,
    ]);

    Http::fake([
        'https://logto.test/oidc/token' => Http::response(['access_token' => 'm2m-token'], 200),
        'https://logto.test/api/users' => Http::response(['id' => 'logto-new-id'], 200),
        'https://logto.test/api/users/*' => Http::response([], 200),
        'https://logto.test/api/roles*' => Http::response(['items' => [], 'totalCount' => 0], 200),
    ]);

    Mail::fake();

    (new SyncMemberToLogto($member, ['member']))->handle(app(LogtoService::class));

    Http::assertSent(function ($request) {
        return $request->url() === 'https://logto.test/api/users'
            && $request->method() === 'POST'
            && data_get($request->data(), 'username') === 'BIT02322';
    });
});

test('the sync job omits the username when the student id yields no Logto-safe characters', function () {
    $member = User::factory()->create([
        'email' => 'alice.smith@must.ac.mw',
        'first_name' => 'Alice',
        'last_name' => 'Smith',
        'student_id' => '---',
        'logto_id' => null,
    ]);

    Http::fake([
        'https://logto.test/oidc/token' => Http::response(['access_token' => 'm2m-token'], 200),
        'https://logto.test/api/users' => Http::response(['id' => 'logto-new-id'], 200),
        'https://logto.test/api/users/*' => Http::response([], 200),
        'https://logto.test/api/roles*' => Http::response(['items' => [], 'totalCount' => 0], 200),
    ]);

    Mail::fake();

    (new SyncMemberToLogto($member, ['member']))->handle(app(LogtoService::class));

    Http::assertSent(function ($request) {
        return $request->url() === 'https://logto.test/api/users'
            && $request->method() === 'POST'
            && ! array_key_exists('username', $request->data());
    });
});

test('the sync job prefixes a leading digit so the normalized username is Logto-safe', function () {
    $member = User::factory()->create([
        'email' => 'bob.jones@must.ac.mw',
        'first_name' => 'Bob',
        'last_name' => 'Jones',
        'student_id' => '123-CSE',
        'logto_id' => null,
    ]);

    Http::fake([
        'https://logto.test/oidc/token' => Http::response(['access_token' => 'm2m-token'], 200),
        'https://logto.test/api/users' => Http::response(['id' => 'logto-new-id'], 200),
        'https://logto.test/api/users/*' => Http::response([], 200),
        'https://logto.test/api/roles*' => Http::response(['items' => [], 'totalCount' => 0], 200),
    ]);

    Mail::fake();

    (new SyncMemberToLogto($member, ['member']))->handle(app(LogtoService::class));

    Http::assertSent(function ($request) {
        return $request->url() === 'https://logto.test/api/users'
            && $request->method() === 'POST'
            && data_get($request->data(), 'username') === 'u123CSE';
    });
});

test('profile resync sends a Logto-safe username for existing members', function () {
    $member = User::factory()->create([
        'first_name' => 'Axel',
        'last_name' => 'Wolff',
        'student_id' => 'STU-1503',
        'logto_id' => 'existing-logto-id',
    ]);

    Http::fake([
        'https://logto.test/oidc/token' => Http::response(['access_token' => 'm2m-token'], 200),
        'https://logto.test/api/users/existing-logto-id' => Http::response(['id' => 'existing-logto-id'], 200),
    ]);

    Mail::fake();

    (new SyncMemberToLogto($member, []))->handle(app(LogtoService::class));

    Http::assertSent(function ($request) {
        return $request->url() === 'https://logto.test/api/users/existing-logto-id'
            && $request->method() === 'PUT'
            && data_get($request->data(), 'username') === 'STU1503';
    });
});

test('the sync job still emails the temporary password when Logto user creation fails', function () {
    $member = User::factory()->create([
        'email' => 'carol.danvers@must.ac.mw',
        'first_name' => 'Carol',
        'last_name' => 'Danvers',
        'student_id' => 'BIT-005-22',
        'logto_id' => null,
    ]);

    Http::fake([
        'https://logto.test/oidc/token' => Http::response(['access_token' => 'm2m-token'], 200),
        'https://logto.test/api/users' => Http::response(['message' => 'unavailable'], 503),
    ]);

    Mail::fake();

    (new SyncMemberToLogto($member, ['member']))->handle(app(LogtoService::class));

    expect($member->refresh()->logto_id)->toBeNull();

    Mail::assertSent(MemberWelcomeMail::class, function (MemberWelcomeMail $mail) use ($member) {
        return $mail->hasTo($member->email)
            && strlen($mail->temporaryPassword) >= 14;
    });
});

test('the sync job is a no-op for members that already have a Logto ID', function () {
    $member = User::factory()->create([
        'email' => 'alice.smith@must.ac.mw',
        'logto_id' => 'existing-logto-id',
    ]);

    Http::fake([
        'https://logto.test/oidc/token' => Http::response(['access_token' => 'm2m-token'], 200),
        'https://logto.test/api/users/existing-logto-id' => Http::response(['id' => 'existing-logto-id'], 200),
        'https://logto.test/api/roles*' => Http::response(['items' => [], 'totalCount' => 0], 200),
        'https://logto.test/api/users/*' => Http::response([], 200),
    ]);

    Mail::fake();

    (new SyncMemberToLogto($member, ['admin']))->handle(app(LogtoService::class));

    expect($member->refresh()->logto_id)->toBe('existing-logto-id');

    Mail::assertNothingSent();
});
