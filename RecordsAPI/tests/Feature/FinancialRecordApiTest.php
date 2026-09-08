<?php

use App\Models\FinancialCategory;
use App\Models\FinancialRecord;
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

function financeToken(array $keys, string $logtoId = 'logto-finance'): string
{
    return JwtTestHelper::sign(JwtTestHelper::claims($logtoId), $keys['private_pem'], $keys['kid']);
}

function createExecutiveRole(): Role
{
    $role = Role::create(['name' => 'executive', 'guard_name' => 'logto']);

    foreach (['financials.create', 'financials.update', 'financials.delete', 'financials.export'] as $perm) {
        $role->givePermissionTo(Permission::findOrCreate($perm, 'logto'));
    }

    return $role;
}

test('financial routes require authentication', function () {
    $this->getJson('/api/v1/financial-records')->assertStatus(401);
    $this->postJson('/api/v1/financial-records', [])->assertStatus(401);
    $this->getJson('/api/v1/financial-categories')->assertStatus(401);
});

test('any authenticated member can list financial records', function () {
    User::factory()->create(['logto_id' => 'logto-viewer']);

    $income = FinancialRecord::factory()->create([
        'type' => 'income',
        'amount' => '1000.00',
        'transaction_date' => '2026-08-01',
    ]);
    $expense = FinancialRecord::factory()->create([
        'type' => 'expense',
        'amount' => '250.00',
        'transaction_date' => '2026-09-01',
    ]);

    $this->withHeader('Authorization', 'Bearer '.financeToken($this->keys, 'logto-viewer'))
        ->getJson('/api/v1/financial-records')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $expense->id)
        ->assertJsonPath('data.1.id', $income->id);
});

test('financial records can be filtered by type and category', function () {
    User::factory()->create(['logto_id' => 'logto-viewer']);

    $fees = FinancialCategory::factory()->create(['name' => 'Membership Fees']);
    FinancialRecord::factory()->create(['type' => 'income', 'category_id' => $fees->id, 'title' => 'Member Dues']);
    FinancialRecord::factory()->create(['type' => 'expense']);

    $this->withHeader('Authorization', 'Bearer '.financeToken($this->keys, 'logto-viewer'))
        ->getJson('/api/v1/financial-records?type=income&category_id='.$fees->id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Member Dues');
});

test('creating a financial record requires title, type, amount, and date', function () {
    $executive = User::factory()->create(['logto_id' => 'logto-finance']);
    $executive->assignRole(createExecutiveRole());

    $this->withHeader('Authorization', 'Bearer '.financeToken($this->keys))
        ->postJson('/api/v1/financial-records', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['title', 'type', 'amount', 'transaction_date']);
});

test('executive can create a financial record and it records the author', function () {
    $executive = User::factory()->create(['logto_id' => 'logto-finance', 'first_name' => 'Patsy']);
    $executive->assignRole(createExecutiveRole());
    $fees = FinancialCategory::factory()->create(['name' => 'Membership Fees']);

    $this->withHeader('Authorization', 'Bearer '.financeToken($this->keys))
        ->postJson('/api/v1/financial-records', [
            'title' => 'Registration Fees',
            'type' => 'income',
            'amount' => '5000.00',
            'transaction_date' => '2026-09-01',
            'category_id' => $fees->id,
        ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Registration Fees')
        ->assertJsonPath('data.type', 'income')
        ->assertJsonPath('data.category.name', 'Membership Fees')
        ->assertJsonPath('data.recorded_by.id', $executive->id);

    expect(FinancialRecord::where('title', 'Registration Fees')->firstOrFail()->recorded_by)->toBe($executive->id);
});

test('member cannot create financial records', function () {
    Role::create(['name' => 'member', 'guard_name' => 'logto']);
    $member = User::factory()->create(['logto_id' => 'logto-member']);
    $member->assignRole(Role::where('name', 'member')->where('guard_name', 'logto')->first());

    $this->withHeader('Authorization', 'Bearer '.financeToken($this->keys, 'logto-member'))
        ->postJson('/api/v1/financial-records', [
            'title' => 'Nope',
            'type' => 'income',
            'amount' => '10.00',
            'transaction_date' => '2026-09-01',
        ])
        ->assertForbidden();
});

test('executive can update and delete a financial record', function () {
    $executive = User::factory()->create(['logto_id' => 'logto-finance']);
    $executive->assignRole(createExecutiveRole());
    $token = 'Bearer '.financeToken($this->keys);

    $record = FinancialRecord::factory()->create(['title' => 'Old Title']);

    $this->withHeader('Authorization', $token)
        ->putJson("/api/v1/financial-records/{$record->id}", [
            'title' => 'New Title',
            'type' => 'expense',
            'amount' => '99.00',
            'transaction_date' => '2026-09-02',
        ])
        ->assertOk()
        ->assertJsonPath('data.title', 'New Title');

    $this->withHeader('Authorization', $token)
        ->deleteJson("/api/v1/financial-records/{$record->id}")
        ->assertOk();

    expect(FinancialRecord::find($record->id))->toBeNull();
});

test('summary returns income, expense, and balance', function () {
    User::factory()->create(['logto_id' => 'logto-viewer']);

    FinancialRecord::factory()->create(['type' => 'income', 'amount' => '1000.00']);
    FinancialRecord::factory()->create(['type' => 'income', 'amount' => '500.00']);
    FinancialRecord::factory()->create(['type' => 'expense', 'amount' => '300.00']);

    $this->withHeader('Authorization', 'Bearer '.financeToken($this->keys, 'logto-viewer'))
        ->getJson('/api/v1/financial-records/summary')
        ->assertOk()
        ->assertJsonPath('data.total_income', '1500.00')
        ->assertJsonPath('data.total_expense', '300.00')
        ->assertJsonPath('data.balance', '1200.00');
});
