<?php

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
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

    $this->seed(RolesAndPermissionsSeeder::class);

    $this->yearRep = User::withoutEvents(fn (): User => User::factory()->create(['logto_id' => 'logto-yearrep-write']))
        ->syncRoles(Role::findOrCreate('year_rep', 'logto'));
});

function yearRepWriteToken(array $claims, array $keys): string
{
    return JwtTestHelper::sign($claims, $keys['private_pem'], $keys['kid']);
}

function yearRepWriteAuth(string $logtoId, array $keys): array
{
    return [['Authorization' => 'Bearer '.yearRepWriteToken(JwtTestHelper::claims($logtoId), $keys)]];
}

test('year reps are forbidden from creating events', function () {
    [$header] = yearRepWriteAuth('logto-yearrep-write', $this->keys);

    $this->withHeaders($header)
        ->postJson('/api/v1/events', ['title' => 'Blocked event', 'event_date' => '2026-12-01'])
        ->assertForbidden();
});

test('year reps are forbidden from updating events', function () {
    $event = Event::factory()->create();
    [$header] = yearRepWriteAuth('logto-yearrep-write', $this->keys);

    $this->withHeaders($header)
        ->putJson("/api/v1/events/{$event->id}", ['title' => 'Blocked update'])
        ->assertForbidden();
});

test('year reps are forbidden from deleting events', function () {
    $event = Event::factory()->create();
    [$header] = yearRepWriteAuth('logto-yearrep-write', $this->keys);

    $this->withHeaders($header)
        ->deleteJson("/api/v1/events/{$event->id}")
        ->assertForbidden();
});

test('year reps are forbidden from creating documents', function () {
    [$header] = yearRepWriteAuth('logto-yearrep-write', $this->keys);

    $this->withHeaders($header)
        ->postJson('/api/v1/documents', ['title' => 'Blocked document'])
        ->assertForbidden();
});

test('year reps are forbidden from updating documents', function () {
    $document = Document::factory()->create();
    [$header] = yearRepWriteAuth('logto-yearrep-write', $this->keys);

    $this->withHeaders($header)
        ->putJson("/api/v1/documents/{$document->id}", ['title' => 'Blocked update'])
        ->assertForbidden();
});

test('year reps are forbidden from deleting documents', function () {
    $document = Document::factory()->create();
    [$header] = yearRepWriteAuth('logto-yearrep-write', $this->keys);

    $this->withHeaders($header)
        ->deleteJson("/api/v1/documents/{$document->id}")
        ->assertForbidden();
});

test('year reps are forbidden from uploading document versions', function () {
    $document = Document::factory()->create();
    [$header] = yearRepWriteAuth('logto-yearrep-write', $this->keys);

    $this->withHeaders($header)
        ->postJson("/api/v1/documents/{$document->id}/versions", ['file' => 'blocked'])
        ->assertForbidden();
});

test('year reps are forbidden from managing document categories', function () {
    $category = DocumentCategory::factory()->create();
    [$header] = yearRepWriteAuth('logto-yearrep-write', $this->keys);

    $this->withHeaders($header)
        ->postJson('/api/v1/document-categories', ['name' => 'Blocked category'])
        ->assertForbidden();

    $this->withHeaders($header)
        ->putJson("/api/v1/document-categories/{$category->id}", ['name' => 'Blocked rename'])
        ->assertForbidden();

    $this->withHeaders($header)
        ->deleteJson("/api/v1/document-categories/{$category->id}")
        ->assertForbidden();
});

test('year reps can still view events and documents', function () {
    $event = Event::factory()->create();
    $document = Document::factory()->create();
    [$header] = yearRepWriteAuth('logto-yearrep-write', $this->keys);

    $this->withHeaders($header)
        ->getJson('/api/v1/events')
        ->assertOk();

    $this->withHeaders($header)
        ->getJson("/api/v1/events/{$event->id}")
        ->assertOk();

    $this->withHeaders($header)
        ->getJson('/api/v1/documents')
        ->assertOk();

    $this->withHeaders($header)
        ->getJson("/api/v1/documents/{$document->id}")
        ->assertOk();
});
