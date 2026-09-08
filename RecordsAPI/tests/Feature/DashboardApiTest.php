<?php

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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

function dashboardToken(array $claims, array $keys): string
{
    return JwtTestHelper::sign($claims, $keys['private_pem'], $keys['kid']);
}

test('dashboard summary requires authentication', function () {
    $this->getJson('/api/v1/dashboard/summary')->assertStatus(401);
});

test('dashboard summary returns record counts', function () {
    $dashboardUser = User::factory()->create(['logto_id' => 'logto-dashboard']);
    User::factory()->count(2)->create();

    Event::factory()->count(2)->create(['created_by' => $dashboardUser->id]);

    $category = DocumentCategory::factory()->create();
    Document::factory()->count(4)->create([
        'category_id' => $category->id,
        'created_by' => $dashboardUser->id,
    ]);

    $this->withHeader('Authorization', 'Bearer '.dashboardToken(JwtTestHelper::claims('logto-dashboard'), $this->keys))
        ->getJson('/api/v1/dashboard/summary')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.members', 3)
        ->assertJsonPath('data.events', 2)
        ->assertJsonPath('data.documents', 4)
        ->assertJsonPath('data.document_categories', 1);
});
