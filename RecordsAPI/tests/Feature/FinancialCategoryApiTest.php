<?php

use App\Models\FinancialCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
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

    $this->keys = JwtTestHelper::keyPair();

    Http::fake([
        'https://logto.test/oidc/jwks' => Http::response([
            'keys' => [JwtTestHelper::jwk($this->keys['public_pem'], $this->keys['kid'])],
        ]),
    ]);
});

function financeCategoryToken(array $keys, string $logtoId = 'logto-finance'): string
{
    return JwtTestHelper::sign(JwtTestHelper::claims($logtoId), $keys['private_pem'], $keys['kid']);
}

function createCategoryExecutiveRole(): Role
{
    $role = Role::create(['name' => 'executive', 'guard_name' => 'logto']);

    foreach (['financials.view', 'financials.update', 'financials.delete'] as $perm) {
        $role->givePermissionTo(Permission::findOrCreate($perm, 'logto'));
    }

    return $role;
}

test('executive can list financial categories', function () {
    $viewer = User::factory()->create(['logto_id' => 'logto-finance']);
    $viewer->syncRoles(createCategoryExecutiveRole());
    FinancialCategory::factory()->create(['name' => 'B']);
    FinancialCategory::factory()->create(['name' => 'A']);

    $this->withHeader('Authorization', 'Bearer '.financeCategoryToken($this->keys, 'logto-finance'))
        ->getJson('/api/v1/financial-categories')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'A')
        ->assertJsonPath('data.1.name', 'B');
});

test('executive can create and update a financial category', function () {
    $executive = User::factory()->create(['logto_id' => 'logto-finance']);
    $executive->syncRoles(createCategoryExecutiveRole());
    $token = 'Bearer '.financeCategoryToken($this->keys);

    $this->withHeader('Authorization', $token)
        ->postJson('/api/v1/financial-categories', ['name' => 'Equipment'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Equipment');

    $category = FinancialCategory::where('name', 'Equipment')->firstOrFail();

    $this->withHeader('Authorization', $token)
        ->putJson("/api/v1/financial-categories/{$category->id}", ['name' => 'Equipment Upgrades'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Equipment Upgrades');
});

test('executive can delete a financial category and records are nulled', function () {
    $executive = User::factory()->create(['logto_id' => 'logto-finance']);
    $executive->syncRoles(createCategoryExecutiveRole());
    $token = 'Bearer '.financeCategoryToken($this->keys);

    $category = FinancialCategory::factory()->create(['name' => 'Temp']);

    $this->withHeader('Authorization', $token)
        ->deleteJson("/api/v1/financial-categories/{$category->id}")
        ->assertOk();

    expect(FinancialCategory::find($category->id))->toBeNull();
});

test('member cannot create financial categories', function () {
    Role::findOrCreate('member', 'logto');
    $member = User::factory()->create(['logto_id' => 'logto-member']);
    $member->syncRoles(Role::where('name', 'member')->where('guard_name', 'logto')->first());

    $this->withHeader('Authorization', 'Bearer '.financeCategoryToken($this->keys, 'logto-member'))
        ->postJson('/api/v1/financial-categories', ['name' => 'Nope'])
        ->assertForbidden();
});
