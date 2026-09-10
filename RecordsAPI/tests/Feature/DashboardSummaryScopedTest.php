<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\Support\JwtTestHelper;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.logto.endpoint' => 'https://logto.test',
        'services.logto.issuer' => 'https://logto.test/oidc',
        'services.logto.api_resource' => 'https://api.test',
    ]);

    Cache::flush();

    Role::findOrCreate('member', 'web');

    $this->keys = JwtTestHelper::keyPair();

    Http::fake([
        'https://logto.test/oidc/jwks' => Http::response([
            'keys' => [JwtTestHelper::jwk($this->keys['public_pem'], $this->keys['kid'])],
        ]),
    ]);

    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
});

function dashboardAccessToken(array $claims, array $keys): string
{
    return JwtTestHelper::sign($claims, $keys['private_pem'], $keys['kid']);
}

function dashboardRoleUser(string $logtoId, string $roleName): User
{
    return User::withoutEvents(fn (): User => User::factory()->create(['logto_id' => $logtoId]))
        ->assignRole(Role::findOrCreate($roleName, 'logto'));
}

function dashboardGetSummary(string $logtoId, array $keys): array
{
    return test()->withHeader('Authorization', 'Bearer '.dashboardAccessToken(JwtTestHelper::claims($logtoId), $keys))
        ->getJson('/api/v1/dashboard/summary')
        ->assertOk()
        ->json('data');
}

test('superadmin summary includes every dashboard section', function () {
    User::factory()->count(3)->create();
    dashboardRoleUser('logto-superadmin', 'superadmin');

    App\Models\Event::factory()->create(['is_published' => true]);
    App\Models\Event::factory()->create(['is_published' => true]);
    App\Models\Event::factory()->create(['is_published' => false]);
    App\Models\Document::factory()->create(['is_public' => true]);
    App\Models\Document::factory()->create(['is_public' => false]);

    $data = dashboardGetSummary('logto-superadmin', $this->keys);

    expect($data)->toHaveKeys([
        'members',
        'events',
        'documents',
        'document_categories',
        'assets',
        'assets_on_loan',
        'financial_records',
        'total_income',
        'total_expense',
        'recent_activity',
        'recent_documents',
    ]);

    expect($data['events'])->toBe(3);
    expect($data['documents'])->toBe(2);
});

test('executive summary omits sections requiring permissions they lack', function () {
    dashboardRoleUser('logto-executive', 'executive');

    $data = dashboardGetSummary('logto-executive', $this->keys);

    expect($data)->toHaveKeys([
        'members',
        'events',
        'documents',
        'document_categories',
        'assets',
        'assets_on_loan',
        'financial_records',
        'total_income',
        'total_expense',
        'recent_documents',
    ])->not->toHaveKey('recent_activity');
});

test('year rep summary includes all events and documents as a board member', function () {
    dashboardRoleUser('logto-yearrep', 'year_rep');

    $privateDoc = App\Models\Document::factory()->create(['is_public' => true, 'title' => 'Public Doc']);
    App\Models\Document::factory()->create(['is_public' => true, 'title' => 'Public Doc 2']);
    App\Models\Document::factory()->create(['is_public' => false, 'title' => 'Private Doc']);
    App\Models\Event::factory()->create(['is_published' => true, 'title' => 'Public Event']);
    App\Models\Event::factory()->create(['is_published' => true, 'title' => 'Public Event 2']);
    App\Models\Event::factory()->create(['is_published' => false, 'title' => 'Draft Event']);

    $data = dashboardGetSummary('logto-yearrep', $this->keys);

    expect($data)->toHaveKeys([
        'events',
        'documents',
        'document_categories',
        'recent_documents',
    ])->not->toHaveKeys([
        'members',
        'assets',
        'assets_on_loan',
        'financial_records',
        'total_income',
        'total_expense',
        'recent_activity',
    ]);

    expect($data['events'])->toBe(3);
    expect($data['documents'])->toBe(3);
    expect(array_column($data['recent_documents'], 'id'))->toContain($privateDoc->id);
});

test('users without dashboard.view get 403 on the summary', function () {
    dashboardRoleUser('logto-member', 'member');
    dashboardRoleUser('logto-alumni', 'alumni');

    $this->withHeader('Authorization', 'Bearer '.dashboardAccessToken(JwtTestHelper::claims('logto-member'), $this->keys))
        ->getJson('/api/v1/dashboard/summary')
        ->assertForbidden();

    $this->withHeader('Authorization', 'Bearer '.dashboardAccessToken(JwtTestHelper::claims('logto-alumni'), $this->keys))
        ->getJson('/api/v1/dashboard/summary')
        ->assertForbidden();
});