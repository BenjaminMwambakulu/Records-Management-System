<?php

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\User;
use App\Support\DocumentFileUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
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

function documentToken(array $claims, array $keys): string
{
    return JwtTestHelper::sign($claims, $keys['private_pem'], $keys['kid']);
}

test('document routes require authentication', function () {
    $this->getJson('/api/v1/documents')->assertStatus(401);
    $this->postJson('/api/v1/documents', [])->assertStatus(401);
});

test('creating a document requires a title and file', function () {
    $admin = User::factory()->create(['logto_id' => 'logto-admin']);
    $admin->assignRole(Role::create(['name' => 'admin', 'guard_name' => 'logto']));

    $this->withHeader('Authorization', 'Bearer '.documentToken(JwtTestHelper::claims('logto-admin'), $this->keys))
        ->post('/api/v1/documents', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['title', 'file']);
});

test('any authenticated member can list documents', function () {
    Storage::fake('public');

    User::factory()->create(['logto_id' => 'logto-viewer']);

    $older = Document::factory()->create(['title' => 'Minutes 2025', 'is_public' => true]);
    $older->versions()->create([
        'version_number' => '1',
        'uploaded_by' => User::factory()->create()->id,
    ])->addMedia(UploadedFile::fake()->create('minutes.pdf', 100))->toMediaCollection('file');

    $newer = Document::factory()->create(['title' => 'Minutes 2026', 'is_public' => true]);

    $older->forceFill(['created_at' => now()->subDay()])->save();
    $newer->forceFill(['created_at' => now()])->save();

    $this->withHeader('Authorization', 'Bearer '.documentToken(JwtTestHelper::claims('logto-viewer'), $this->keys))
        ->getJson('/api/v1/documents')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $newer->id)
        ->assertJsonPath('data.1.id', $older->id)
        ->assertJsonPath('data.1.latest_version.version_number', '1')
        ->assertJsonPath('data.1.latest_version.file_url', fn (string $url) => str_contains($url, '/storage/'))
        ->assertJsonPath('data.0.latest_version', null);
});

test('any authenticated member can view a document with its versions', function () {
    Storage::fake('public');

    $viewer = User::factory()->create(['logto_id' => 'logto-viewer']);
    $category = DocumentCategory::factory()->create(['name' => 'Policies']);
    $document = Document::factory()->create(['title' => 'Constitution', 'category_id' => $category->id, 'is_public' => true]);

    $v1 = $document->versions()->create(['version_number' => '1', 'uploaded_by' => $viewer->id]);
    $v1->addMedia(UploadedFile::fake()->create('constitution.pdf', 100))->toMediaCollection('file');

    $this->withHeader('Authorization', 'Bearer '.documentToken(JwtTestHelper::claims('logto-viewer'), $this->keys))
        ->getJson("/api/v1/documents/{$document->id}")
        ->assertOk()
        ->assertJsonPath('data.title', 'Constitution')
        ->assertJsonPath('data.category.name', 'Policies')
        ->assertJsonCount(1, 'data.versions')
        ->assertJsonPath('data.versions.0.version_number', '1')
        ->assertJsonPath('data.versions.0.file_url', fn (string $url) => str_contains($url, '/storage/'));
});

test('documents can be filtered by category', function () {
    Storage::fake('public');

    User::factory()->create(['logto_id' => 'logto-viewer']);

    $policies = DocumentCategory::factory()->create(['name' => 'Policies']);
    $minutes = DocumentCategory::factory()->create(['name' => 'Minutes']);

    Document::factory()->create(['title' => 'Policy Doc', 'category_id' => $policies->id, 'is_public' => true]);
    Document::factory()->create(['title' => 'Meeting Minutes', 'category_id' => $minutes->id, 'is_public' => true]);

    $this->withHeader('Authorization', 'Bearer '.documentToken(JwtTestHelper::claims('logto-viewer'), $this->keys))
        ->getJson('/api/v1/documents?category_id='.$policies->id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Policy Doc');
});

test('members cannot create documents or upload versions', function () {
    Role::create(['name' => 'member', 'guard_name' => 'logto']);
    $member = User::factory()->create(['logto_id' => 'logto-member']);
    $member->assignRole(Role::where('name', 'member')->where('guard_name', 'logto')->first());

    $document = Document::factory()->create();
    $token = 'Bearer '.documentToken(JwtTestHelper::claims('logto-member'), $this->keys);

    $this->withHeader('Authorization', $token)
        ->post('/api/v1/documents', ['title' => 'Nope', 'file' => UploadedFile::fake()->create('nope.pdf', 100)])
        ->assertForbidden();

    $this->withHeader('Authorization', $token)
        ->post("/api/v1/documents/{$document->id}/versions", ['file' => UploadedFile::fake()->create('nope.pdf', 100)])
        ->assertForbidden();
});

test('admins can create a document with its first version', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['logto_id' => 'logto-admin']);
    $admin->assignRole(Role::create(['name' => 'admin', 'guard_name' => 'logto']));

    $category = DocumentCategory::factory()->create();

    $this->withHeader('Authorization', 'Bearer '.documentToken(JwtTestHelper::claims('logto-admin'), $this->keys))
        ->post('/api/v1/documents', [
            'title' => 'Constitution',
            'category_id' => $category->id,
            'status' => 'active',
            'is_public' => true,
            'file' => UploadedFile::fake()->create('constitution.pdf', 100),
        ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Constitution')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.category.id', $category->id)
        ->assertJsonPath('data.latest_version.version_number', '1')
        ->assertJsonPath('data.latest_version.file_url', fn (string $url) => str_contains($url, '/storage/'))
        ->assertJsonPath('data.created_by', $admin->id);

    $document = Document::where('title', 'Constitution')->first();

    expect($document->versions()->count())->toBe(1)
        ->and($document->latestVersion->version_number)->toBe('1')
        ->and($document->latestVersion->getFirstMediaPath('file'))->not->toBeNull();
});

test('admins can upload a newer version and it auto-increments', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['logto_id' => 'logto-admin']);
    $admin->assignRole(Role::create(['name' => 'admin', 'guard_name' => 'logto']));

    $document = Document::factory()->create(['is_public' => true]);
    $document->versions()->create(['version_number' => '1', 'uploaded_by' => $admin->id])
        ->addMedia(UploadedFile::fake()->create('v1.pdf', 100))->toMediaCollection('file');

    $this->withHeader('Authorization', 'Bearer '.documentToken(JwtTestHelper::claims('logto-admin'), $this->keys))
        ->post("/api/v1/documents/{$document->id}/versions", [
            'file' => UploadedFile::fake()->create('v2.pdf', 100),
            'change_summary' => 'Fixed typo',
        ])
        ->assertCreated()
        ->assertJsonPath('data.version_number', '2')
        ->assertJsonPath('data.change_summary', 'Fixed typo')
        ->assertJsonPath('data.file_url', fn (string $url) => str_contains($url, '/storage/'));

    expect($document->versions()->count())->toBe(2);
});

test('members can list and view document versions', function () {
    Storage::fake('public');

    $viewer = User::factory()->create(['logto_id' => 'logto-viewer']);

    $document = Document::factory()->create(['is_public' => true]);
    $document->versions()->create(['version_number' => '1', 'uploaded_by' => $viewer->id])
        ->addMedia(UploadedFile::fake()->create('v1.pdf', 100))->toMediaCollection('file');
    $document->versions()->create(['version_number' => '2', 'uploaded_by' => $viewer->id])
        ->addMedia(UploadedFile::fake()->create('v2.pdf', 100))->toMediaCollection('file');

    $this->withHeader('Authorization', 'Bearer '.documentToken(JwtTestHelper::claims('logto-viewer'), $this->keys))
        ->getJson("/api/v1/documents/{$document->id}/versions")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.version_number', '1')
        ->assertJsonPath('data.1.version_number', '2');

    $v2 = $document->versions()->where('version_number', '2')->first();

    $this->withHeader('Authorization', 'Bearer '.documentToken(JwtTestHelper::claims('logto-viewer'), $this->keys))
        ->getJson("/api/v1/documents/{$document->id}/versions/{$v2->id}")
        ->assertOk()
        ->assertJsonPath('data.version_number', '2')
        ->assertJsonPath('data.file_url', fn (string $url) => str_contains($url, '/storage/'));
});

test('admins can update a document', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['logto_id' => 'logto-admin']);
    $admin->assignRole(Role::create(['name' => 'admin', 'guard_name' => 'logto']));

    $document = Document::factory()->create(['title' => 'Old Title']);

    $this->withHeader('Authorization', 'Bearer '.documentToken(JwtTestHelper::claims('logto-admin'), $this->keys))
        ->putJson("/api/v1/documents/{$document->id}", [
            'title' => 'New Title',
            'status' => 'archived',
        ])
        ->assertOk()
        ->assertJsonPath('data.title', 'New Title')
        ->assertJsonPath('data.status', 'archived');

    expect($document->refresh()->title)->toBe('New Title');
});

test('admins can delete a document and its versions', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['logto_id' => 'logto-admin']);
    $admin->assignRole(Role::create(['name' => 'admin', 'guard_name' => 'logto']));

    $document = Document::factory()->create();
    $version = $document->versions()->create(['version_number' => '1', 'uploaded_by' => $admin->id]);
    $media = $version->addMedia(UploadedFile::fake()->create('doc.pdf', 100))->toMediaCollection('file');

    $this->withHeader('Authorization', 'Bearer '.documentToken(JwtTestHelper::claims('logto-admin'), $this->keys))
        ->deleteJson("/api/v1/documents/{$document->id}")
        ->assertOk()
        ->assertJsonPath('message', 'Document deleted successfully');

    expect(Document::find($document->id))->toBeNull()
        ->and(DocumentVersion::find($version->id))->toBeNull()
        ->and(Media::find($media->id))->toBeNull();
});

test('documents default to private and expose the public flag', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['logto_id' => 'logto-admin']);
    $admin->assignRole(Role::create(['name' => 'admin', 'guard_name' => 'logto']));

    $this->withHeader('Authorization', 'Bearer '.documentToken(JwtTestHelper::claims('logto-admin'), $this->keys))
        ->post('/api/v1/documents', [
            'title' => 'Members Only Minutes',
            'file' => UploadedFile::fake()->create('minutes.pdf', 100),
        ])
        ->assertCreated()
        ->assertJsonPath('data.is_public', false);
});

test('is_public is honored when creating a document', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['logto_id' => 'logto-admin']);
    $admin->assignRole(Role::create(['name' => 'admin', 'guard_name' => 'logto']));

    $this->withHeader('Authorization', 'Bearer '.documentToken(JwtTestHelper::claims('logto-admin'), $this->keys))
        ->post('/api/v1/documents', [
            'title' => 'Public Constitution',
            'is_public' => true,
            'file' => UploadedFile::fake()->create('constitution.pdf', 100),
        ])
        ->assertCreated()
        ->assertJsonPath('data.is_public', true);

    expect(Document::where('title', 'Public Constitution')->firstOrFail()->is_public)->toBeTrue();
});

test('members only see public documents in the list', function () {
    Storage::fake('public');

    Role::create(['name' => 'member', 'guard_name' => 'logto']);
    $member = User::factory()->create(['logto_id' => 'logto-member']);
    $member->assignRole(Role::where('name', 'member')->where('guard_name', 'logto')->first());

    Document::factory()->create(['title' => 'Public Doc', 'is_public' => true]);
    Document::factory()->create(['title' => 'Private Doc', 'is_public' => false]);

    $this->withHeader('Authorization', 'Bearer '.documentToken(JwtTestHelper::claims('logto-member'), $this->keys))
        ->getJson('/api/v1/documents')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Public Doc');
});

test('members cannot view private documents', function () {
    Storage::fake('public');

    Role::create(['name' => 'member', 'guard_name' => 'logto']);
    $member = User::factory()->create(['logto_id' => 'logto-member']);
    $member->assignRole(Role::where('name', 'member')->where('guard_name', 'logto')->first());

    $private = Document::factory()->create(['title' => 'Private Doc', 'is_public' => false]);
    $private->versions()->create(['version_number' => '1', 'uploaded_by' => $member->id])
        ->addMedia(UploadedFile::fake()->create('private.pdf', 100))->toMediaCollection('file');
    $public = Document::factory()->create(['title' => 'Public Doc', 'is_public' => true]);

    $token = 'Bearer '.documentToken(JwtTestHelper::claims('logto-member'), $this->keys);

    $this->withHeader('Authorization', $token)
        ->getJson("/api/v1/documents/{$private->id}")
        ->assertNotFound();

    $this->withHeader('Authorization', $token)
        ->getJson("/api/v1/documents/{$private->id}/versions")
        ->assertNotFound();

    $this->withHeader('Authorization', $token)
        ->getJson("/api/v1/documents/{$public->id}")
        ->assertOk()
        ->assertJsonPath('data.is_public', true);
});

test('privileged roles can list and view private documents', function () {
    Storage::fake('public');

    Role::create(['name' => 'executive', 'guard_name' => 'logto']);
    $executive = User::factory()->create(['logto_id' => 'logto-executive']);
    $executive->assignRole(Role::where('name', 'executive')->where('guard_name', 'logto')->first());

    $private = Document::factory()->create(['title' => 'Private Doc', 'is_public' => false]);

    $token = 'Bearer '.documentToken(JwtTestHelper::claims('logto-executive'), $this->keys);

    $this->withHeader('Authorization', $token)
        ->getJson('/api/v1/documents')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $private->id);

    $this->withHeader('Authorization', $token)
        ->getJson("/api/v1/documents/{$private->id}")
        ->assertOk()
        ->assertJsonPath('data.is_public', false);
});

test('year reps as board members can list and view private documents', function () {
    Storage::fake('public');

    Role::findOrCreate('member', 'web');
    Role::create(['name' => 'year_rep', 'guard_name' => 'logto']);
    $yearRep = User::factory()->create(['logto_id' => 'logto-yearrep']);
    $yearRep->assignRole(Role::where('name', 'year_rep')->where('guard_name', 'logto')->first());

    $private = Document::factory()->create(['title' => 'Private Doc', 'is_public' => false]);

    $token = 'Bearer '.documentToken(JwtTestHelper::claims('logto-yearrep'), $this->keys);

    $this->withHeader('Authorization', $token)
        ->getJson('/api/v1/documents')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $private->id);

    $this->withHeader('Authorization', $token)
        ->getJson("/api/v1/documents/{$private->id}")
        ->assertOk()
        ->assertJsonPath('data.is_public', false);
});

test('admins can toggle a document public flag', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['logto_id' => 'logto-admin']);
    $admin->assignRole(Role::create(['name' => 'admin', 'guard_name' => 'logto']));

    $document = Document::factory()->create(['is_public' => false]);

    $token = 'Bearer '.documentToken(JwtTestHelper::claims('logto-admin'), $this->keys);

    $this->withHeader('Authorization', $token)
        ->putJson("/api/v1/documents/{$document->id}", ['is_public' => true])
        ->assertOk()
        ->assertJsonPath('data.is_public', true);

    expect($document->refresh()->is_public)->toBeTrue();

    $this->withHeader('Authorization', $token)
        ->putJson("/api/v1/documents/{$document->id}", ['is_public' => false])
        ->assertOk()
        ->assertJsonPath('data.is_public', false);

    expect($document->refresh()->is_public)->toBeFalse();
});

test('private document files are stored privately and served via a signed url', function () {
    Storage::fake('public');
    Storage::fake('local');

    $admin = User::factory()->create(['logto_id' => 'logto-admin']);
    $admin->assignRole(Role::create(['name' => 'admin', 'guard_name' => 'logto']));

    $response = $this->withHeader('Authorization', 'Bearer '.documentToken(JwtTestHelper::claims('logto-admin'), $this->keys))
        ->post('/api/v1/documents', [
            'title' => 'Fee Structure',
            'file' => UploadedFile::fake()->create('fee-structure.pdf', 100),
        ])
        ->assertCreated();

    $document = Document::where('title', 'Fee Structure')->firstOrFail();
    $version = $document->latestVersion;
    $media = $version->getFirstMedia('file');

    expect($media->disk)->toBe('local');
    Storage::disk('local')->assertExists("document-versions/files/{$media->id}/fee-structure.pdf");
    Storage::disk('public')->assertMissing("document-versions/files/{$media->id}/fee-structure.pdf");

    $fileUrl = $response->json('data.latest_version.file_url');

    expect($fileUrl)->toContain("/documents/versions/{$version->id}/file")
        ->and($fileUrl)->toContain('expires')
        ->and($fileUrl)->toContain('token');

    $this->get($fileUrl)->assertOk()->assertHeader('content-type', 'application/pdf');

    $expired = app(DocumentFileUrl::class)->make($version->id, now()->subMinute());

    $this->get($expired)->assertStatus(403);
});

test('toggling the public flag moves document files between disks', function () {
    Storage::fake('public');
    Storage::fake('local');

    $admin = User::factory()->create(['logto_id' => 'logto-admin']);
    $admin->assignRole(Role::create(['name' => 'admin', 'guard_name' => 'logto']));
    $token = 'Bearer '.documentToken(JwtTestHelper::claims('logto-admin'), $this->keys);

    $document = Document::factory()->create(['title' => 'Vote Results', 'is_public' => false]);
    $version = $document->versions()->create(['version_number' => '1', 'uploaded_by' => $admin->id]);
    $version->addMedia(UploadedFile::fake()->create('results.pdf', 100))->toMediaCollection('file', 'local');

    $this->withHeader('Authorization', $token)
        ->putJson("/api/v1/documents/{$document->id}", ['is_public' => true])
        ->assertOk()
        ->assertJsonPath('data.latest_version.file_url', fn (string $url) => str_contains($url, '/storage/'));

    $publicMedia = DocumentVersion::findOrFail($version->id)->getMedia('file')->first();

    expect($publicMedia->disk)->toBe('public');
    Storage::disk('public')->assertExists("document-versions/files/{$publicMedia->id}/results.pdf");
    Storage::disk('local')->assertMissing("document-versions/files/{$publicMedia->id}/results.pdf");

    $this->withHeader('Authorization', $token)
        ->putJson("/api/v1/documents/{$document->id}", ['is_public' => false])
        ->assertOk()
        ->assertJsonPath('data.latest_version.file_url', fn (string $url) => str_contains($url, 'token'));

    $privateMedia = DocumentVersion::findOrFail($version->id)->getMedia('file')->first();

    expect($privateMedia->disk)->toBe('local');
    Storage::disk('local')->assertExists("document-versions/files/{$privateMedia->id}/results.pdf");
    Storage::disk('public')->assertMissing("document-versions/files/{$privateMedia->id}/results.pdf");
});
