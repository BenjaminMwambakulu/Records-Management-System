<?php

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetLoan;
use App\Models\User;
use Carbon\Carbon;
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

    // Create permissions and role
    $this->permissions = [
        'assets.view',
        'assets.create',
        'assets.update',
        'assets.delete',
        'assets.checkout',
        'assets.return',
    ];

    foreach ($this->permissions as $permission) {
        Permission::findOrCreate($permission, 'logto');
    }

    $this->adminRole = Role::findOrCreate('admin', 'logto')
        ->syncPermissions($this->permissions);
});

function authHeaders(array $keys, string $logtoId = 'logto-admin'): array
{
    $token = JwtTestHelper::sign(JwtTestHelper::claims($logtoId), $keys['private_pem'], $keys['kid']);

    return ['Authorization' => "Bearer {$token}"];
}

function createAdminUser(): User
{
    $user = User::factory()->create(['logto_id' => 'logto-admin']);
    $user->assignRole(Role::findOrCreate('admin', 'logto'));

    return $user;
}

it('lists assets', function () {
    createAdminUser();
    Asset::factory()->count(3)->create();

    $this->withHeaders(authHeaders($this->keys))
        ->getJson('/api/v1/assets')
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                ['id', 'name', 'serial_number', 'category', 'status'],
            ],
        ]);
});

it('searches assets by name', function () {
    createAdminUser();
    Asset::factory()->create(['name' => 'Projector X1']);
    Asset::factory()->create(['name' => 'Laptop Pro']);

    $this->withHeaders(authHeaders($this->keys))
        ->getJson('/api/v1/assets?search=projector')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Projector X1');
});

it('filters assets by status', function () {
    createAdminUser();
    Asset::factory()->available()->create();
    Asset::factory()->borrowed()->create();

    $this->withHeaders(authHeaders($this->keys))
        ->getJson('/api/v1/assets?status=available')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('creates an asset', function () {
    createAdminUser();

    $this->withHeaders(authHeaders($this->keys))
        ->postJson('/api/v1/assets', [
            'name' => 'Test Projector',
            'serial_number' => 'PRJ-001',
            'category' => 'Equipment',
        ])
        ->assertCreated()
        ->assertJsonStructure([
            'success',
            'message',
            'data' => ['id', 'name', 'serial_number', 'category', 'status'],
        ]);

    $this->assertDatabaseHas('assets', [
        'name' => 'Test Projector',
        'serial_number' => 'PRJ-001',
        'status' => AssetStatus::AVAILABLE,
    ]);
});

it('validates required fields when creating asset', function () {
    createAdminUser();

    $this->withHeaders(authHeaders($this->keys))
        ->postJson('/api/v1/assets', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'category']);
});

it('shows a single asset', function () {
    createAdminUser();
    $asset = Asset::factory()->create();

    $this->withHeaders(authHeaders($this->keys))
        ->getJson("/api/v1/assets/{$asset->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $asset->id);
});

it('returns 404 for non-existent asset', function () {
    createAdminUser();

    $this->withHeaders(authHeaders($this->keys))
        ->getJson('/api/v1/assets/999')
        ->assertNotFound();
});

it('updates an asset', function () {
    createAdminUser();
    $asset = Asset::factory()->create(['name' => 'Old Name']);

    $this->withHeaders(authHeaders($this->keys))
        ->putJson("/api/v1/assets/{$asset->id}", [
            'name' => 'New Name',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'New Name');
});

it('deletes (retires) an asset', function () {
    createAdminUser();
    $asset = Asset::factory()->available()->create();

    $this->withHeaders(authHeaders($this->keys))
        ->deleteJson("/api/v1/assets/{$asset->id}")
        ->assertOk();

    expect($asset->refresh()->status)->toBe(AssetStatus::RETIRED);
});

it('returns asset summary', function () {
    createAdminUser();
    Asset::factory()->available()->count(2)->create();
    Asset::factory()->borrowed()->create();

    $this->withHeaders(authHeaders($this->keys))
        ->getJson('/api/v1/assets/summary')
        ->assertOk()
        ->assertJsonPath('data.available', 2)
        ->assertJsonPath('data.borrowed', 1);
});

it('returns asset categories', function () {
    createAdminUser();
    Asset::factory()->create(['category' => 'Equipment']);
    Asset::factory()->create(['category' => 'Furniture']);

    $this->withHeaders(authHeaders($this->keys))
        ->getJson('/api/v1/assets/categories')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('checks out an asset', function () {
    $user = createAdminUser();
    $asset = Asset::factory()->available()->create();
    $borrower = User::factory()->create();

    $this->withHeaders(authHeaders($this->keys))
        ->postJson("/api/v1/assets/{$asset->id}/checkout", [
            'borrower_id' => $borrower->id,
            'due_date' => Carbon::now()->addWeek()->toIso8601String(),
        ])
        ->assertCreated()
        ->assertJsonStructure([
            'success',
            'data' => ['id', 'asset_id', 'borrower', 'due_date'],
        ]);

    expect($asset->refresh()->status)->toBe(AssetStatus::BORROWED);
});

it('cannot checkout a borrowed asset', function () {
    createAdminUser();
    $asset = Asset::factory()->borrowed()->create();
    $borrower = User::factory()->create();

    $this->withHeaders(authHeaders($this->keys))
        ->postJson("/api/v1/assets/{$asset->id}/checkout", [
            'borrower_id' => $borrower->id,
            'due_date' => Carbon::now()->addWeek()->toIso8601String(),
        ])
        ->assertStatus(422);
});

it('returns a borrowed asset', function () {
    createAdminUser();
    $asset = Asset::factory()->borrowed()->create();
    AssetLoan::factory()->create([
        'asset_id' => $asset->id,
        'returned_date' => null,
    ]);

    $this->withHeaders(authHeaders($this->keys))
        ->postJson("/api/v1/assets/{$asset->id}/return")
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => ['id', 'returned_date'],
        ]);

    expect($asset->refresh()->status)->toBe(AssetStatus::AVAILABLE);
});

it('returns 404 when returning asset with no active loan', function () {
    createAdminUser();
    $asset = Asset::factory()->available()->create();

    $this->withHeaders(authHeaders($this->keys))
        ->postJson("/api/v1/assets/{$asset->id}/return")
        ->assertNotFound();
});

it('lists loans for an asset', function () {
    createAdminUser();
    $asset = Asset::factory()->create();
    AssetLoan::factory()->count(3)->create(['asset_id' => $asset->id]);

    $this->withHeaders(authHeaders($this->keys))
        ->getJson("/api/v1/assets/{$asset->id}/loans")
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('lists overdue loans', function () {
    createAdminUser();
    AssetLoan::factory()->overdue()->count(2)->create();
    AssetLoan::factory()->create(); // not overdue

    $this->withHeaders(authHeaders($this->keys))
        ->getJson('/api/v1/assets/loans/overdue')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});
