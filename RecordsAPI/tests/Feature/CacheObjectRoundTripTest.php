<?php

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Event;
use App\Models\FinancialCategory;
use App\Models\User;
use App\Services\DocumentCategoryService;
use App\Services\FinancialCategoryService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\Collection;
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
        // Force real serialization on the (test-time) array store while keeping
        // Laravel's default security allowlist (serializable_classes = false),
        // which is what the database store uses in the running application.
        'cache.stores.array.serialize' => true,
    ]);

    // Drop the already-resolved array store so it is rebuilt with the
    // serialization flags above, then clean any stale values.
    Cache::forgetDriver('array');
    Cache::flush();

    $this->keys = JwtTestHelper::keyPair();

    Http::fake([
        'https://logto.test/oidc/jwks' => Http::response([
            'keys' => [JwtTestHelper::jwk($this->keys['public_pem'], $this->keys['kid'])],
        ]),
    ]);

    $this->seed(RolesAndPermissionsSeeder::class);
});

function cacheRoundTripToken(array $claims, array $keys): string
{
    return JwtTestHelper::sign($claims, $keys['private_pem'], $keys['kid']);
}

function cacheRoundTripRoleUser(string $logtoId, string $roleName): User
{
    return User::withoutEvents(fn (): User => User::factory()->create(['logto_id' => $logtoId]))
        ->syncRoles(Role::findOrCreate($roleName, 'logto'));
}

test('document category list returns a real collection after a serialized cache round trip', function () {
    DocumentCategory::factory()->count(2)->create();

    $service = app(DocumentCategoryService::class);
    $service->list(); // prime the cache

    $result = $service->list(); // read the serialized value back

    expect($result)->toBeInstanceOf(Collection::class)
        ->and($result->count())->toBe(2)
        ->and($result->every(fn ($category) => $category instanceof DocumentCategory))->toBeTrue();
});

test('financial category list returns a real collection after a serialized cache round trip', function () {
    FinancialCategory::factory()->count(3)->create();

    $service = app(FinancialCategoryService::class);
    $service->list(); // prime the cache

    $result = $service->list(); // read the serialized value back

    expect($result)->toBeInstanceOf(Collection::class)
        ->and($result->count())->toBe(3)
        ->and($result->every(fn ($category) => $category instanceof FinancialCategory))->toBeTrue();
});

test('dashboard summary renders model-backed sections after a serialized cache round trip', function () {
    cacheRoundTripRoleUser('logto-superadmin', 'superadmin');

    Document::factory()->create(['is_public' => true, 'title' => 'Minutes']);
    Event::factory()->create(['is_published' => true, 'title' => 'Meetup']);

    $token = 'Bearer '.cacheRoundTripToken(JwtTestHelper::claims('logto-superadmin'), $this->keys);

    $this->withHeader('Authorization', $token)
        ->getJson('/api/v1/dashboard/summary')
        ->assertOk(); // prime the cache

    $response = $this->withHeader('Authorization', $token)
        ->getJson('/api/v1/dashboard/summary'); // warm read

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.documents', 1)
        ->assertJsonPath('data.recent_documents.0.title', 'Minutes');
});
