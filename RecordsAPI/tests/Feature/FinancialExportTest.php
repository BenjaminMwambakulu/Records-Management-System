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

function financeExportToken(array $keys, string $logtoId = 'logto-finance'): string
{
    return JwtTestHelper::sign(JwtTestHelper::claims($logtoId), $keys['private_pem'], $keys['kid']);
}

function createExportRole(): Role
{
    $role = Role::create(['name' => 'executive', 'guard_name' => 'logto']);
    $role->givePermissionTo(Permission::findOrCreate('financials.export', 'logto'));

    return $role;
}

test('member cannot export financial records', function () {
    Role::findOrCreate('member', 'logto');
    $member = User::factory()->create(['logto_id' => 'logto-member']);
    $member->syncRoles(Role::where('name', 'member')->where('guard_name', 'logto')->first());

    $this->withHeader('Authorization', 'Bearer '.financeExportToken($this->keys, 'logto-member'))
        ->getJson('/api/v1/financial-records/export')
        ->assertForbidden();
});

test('executive can export financial records as CSV', function () {
    $executive = User::factory()->create(['logto_id' => 'logto-finance']);
    $executive->syncRoles(createExportRole());
    $fees = FinancialCategory::factory()->create(['name' => 'Membership Fees']);
    FinancialRecord::factory()->create([
        'title' => 'Dues',
        'category_id' => $fees->id,
        'type' => 'income',
        'amount' => '500.00',
        'transaction_date' => '2026-09-01',
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.financeExportToken($this->keys))
        ->get('/api/v1/financial-records/export')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $csv = $response->streamedContent();

    expect($csv)->toContain('Title,Category,Type,Amount,"Transaction Date","Recorded By"')
        ->and($csv)->toContain('Dues,"Membership Fees",income,500.00,2026-09-01');
});
