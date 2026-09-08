<?php

use App\Models\Document;
use App\Models\DocumentCategory;
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

    $this->keys = JwtTestHelper::keyPair();

    Http::fake([
        'https://logto.test/oidc/jwks' => Http::response([
            'keys' => [JwtTestHelper::jwk($this->keys['public_pem'], $this->keys['kid'])],
        ]),
    ]);
});

function categoryToken(array $claims, array $keys): string
{
    return JwtTestHelper::sign($claims, $keys['private_pem'], $keys['kid']);
}

function loggedInAdmin()
{
    $admin = User::factory()->create(['logto_id' => 'logto-admin']);
    $admin->assignRole(Role::create(['name' => 'admin', 'guard_name' => 'logto']));

    return $admin;
}

test('document category routes require authentication', function () {
    $this->getJson('/api/v1/document-categories')->assertStatus(401);
    $this->postJson('/api/v1/document-categories', [])->assertStatus(401);
});

test('any authenticated member can list document categories', function () {
    User::factory()->create(['logto_id' => 'logto-viewer']);
    DocumentCategory::factory()->create(['name' => 'Policies']);
    DocumentCategory::factory()->create(['name' => 'Minutes']);

    $this->withHeader('Authorization', 'Bearer '.categoryToken(JwtTestHelper::claims('logto-viewer'), $this->keys))
        ->getJson('/api/v1/document-categories')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Minutes')
        ->assertJsonPath('data.1.name', 'Policies');
});

test('members cannot create, update, or delete document categories', function () {
    Role::create(['name' => 'member', 'guard_name' => 'logto']);
    $member = User::factory()->create(['logto_id' => 'logto-member']);
    $member->assignRole(Role::where('name', 'member')->where('guard_name', 'logto')->first());

    $category = DocumentCategory::factory()->create();

    $token = 'Bearer '.categoryToken(JwtTestHelper::claims('logto-member'), $this->keys);

    $this->withHeader('Authorization', $token)
        ->postJson('/api/v1/document-categories', ['name' => 'Nope'])
        ->assertForbidden();

    $this->withHeader('Authorization', $token)
        ->putJson("/api/v1/document-categories/{$category->id}", ['name' => 'Nope'])
        ->assertForbidden();

    $this->withHeader('Authorization', $token)
        ->deleteJson("/api/v1/document-categories/{$category->id}")
        ->assertForbidden();
});

test('admins can create a document category', function () {
    loggedInAdmin();

    $this->withHeader('Authorization', 'Bearer '.categoryToken(JwtTestHelper::claims('logto-admin'), $this->keys))
        ->postJson('/api/v1/document-categories', [
            'name' => 'Attendance',
            'description' => 'Attendance sheets and registers',
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Attendance')
        ->assertJsonPath('data.description', 'Attendance sheets and registers');

    $this->assertDatabaseHas('document_categories', ['name' => 'Attendance']);
});

test('document category name must be unique', function () {
    loggedInAdmin();
    DocumentCategory::factory()->create(['name' => 'Policies']);

    $this->withHeader('Authorization', 'Bearer '.categoryToken(JwtTestHelper::claims('logto-admin'), $this->keys))
        ->postJson('/api/v1/document-categories', ['name' => 'Policies'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('admins can update a document category', function () {
    loggedInAdmin();
    $category = DocumentCategory::factory()->create(['name' => 'Policies']);

    $this->withHeader('Authorization', 'Bearer '.categoryToken(JwtTestHelper::claims('logto-admin'), $this->keys))
        ->putJson("/api/v1/document-categories/{$category->id}", ['name' => 'Policy Docs'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Policy Docs');

    expect($category->refresh()->name)->toBe('Policy Docs');
});

test('admins can delete a document category and documents fall back to no category', function () {
    Role::create(['name' => 'admin', 'guard_name' => 'logto']);
    $admin = User::factory()->create(['logto_id' => 'logto-admin']);
    $admin->assignRole(Role::where('name', 'admin')->where('guard_name', 'logto')->first());

    $document = Document::factory()->create(['category_id' => $category = DocumentCategory::factory()->create()->id]);

    $this->withHeader('Authorization', 'Bearer '.categoryToken(JwtTestHelper::claims('logto-admin'), $this->keys))
        ->deleteJson("/api/v1/document-categories/{$category}");

    expect(DocumentCategory::find($category))->toBeNull()
        ->and($document->refresh()->category_id)->toBeNull();
});
