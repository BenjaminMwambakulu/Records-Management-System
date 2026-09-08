<?php

use App\Jobs\ImportMembersFromFile;
use App\Models\MemberImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\Support\JwtTestHelper;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.logto.endpoint' => 'https://logto.test',
        'services.logto.issuer' => 'https://logto.test/oidc',
        'services.logto.api_resource' => 'https://api.test',
    ]);

    $this->keys = JwtTestHelper::keyPair();

    Http::fake([
        'https://logto.test/oidc/jwks' => Http::response([
            'keys' => [JwtTestHelper::jwk($this->keys['public_pem'], $this->keys['kid'])],
        ]),
    ]);

    Storage::fake('local');
    Queue::fake();
});

function importToken(array $claims, array $keys): string
{
    return JwtTestHelper::sign($claims, $keys['private_pem'], $keys['kid']);
}

test('member imports require authentication', function () {
    $this->postJson('/api/v1/members/import', ['file' => UploadedFile::fake()->create('import.csv')])
        ->assertUnauthorized();
});

test('non-admins cannot upload an import file', function () {
    Role::create(['name' => 'member', 'guard_name' => 'logto']);
    $member = User::factory()->create(['logto_id' => 'logto-member']);
    $member->assignRole(Role::where('name', 'member')->where('guard_name', 'logto')->first());

    $file = UploadedFile::fake()->createWithContent('import.csv', "student_id,first_name,last_name,email\nA,B,C,d@e.com");

    $this->withHeader('Authorization', 'Bearer '.importToken(JwtTestHelper::claims('logto-member'), $this->keys))
        ->postJson('/api/v1/members/import', ['file' => $file])
        ->assertForbidden();
});

test('admin can upload a csv and queue an import job', function () {
    Role::create(['name' => 'admin', 'guard_name' => 'logto']);
    $admin = User::factory()->create(['logto_id' => 'logto-admin']);
    $admin->assignRole(Role::where('name', 'admin')->where('guard_name', 'logto')->first());

    $csv = "student_id,first_name,last_name,email\nIMPORT-001,Import,User,import@must.ac.mw";
    $file = UploadedFile::fake()->createWithContent('import.csv', $csv);

    $this->withHeader('Authorization', 'Bearer '.importToken(JwtTestHelper::claims('logto-admin'), $this->keys))
        ->postJson('/api/v1/members/import', ['file' => $file])
        ->assertAccepted()
        ->assertJsonPath('data.status', MemberImport::STATUS_PENDING)
        ->assertJsonPath('data.original_name', 'import.csv');

    Queue::assertPushed(ImportMembersFromFile::class);

    $import = MemberImport::latest('id')->first();
    expect($import)->not->toBeNull()
        ->and($import->user_id)->toBe($admin->id)
        ->and($import->file_path)->not->toBeNull()
        ->and(Storage::disk('local')->exists($import->file_path))->toBeTrue();
});

test('admin can download the import template', function () {
    Role::create(['name' => 'admin', 'guard_name' => 'logto']);
    $admin = User::factory()->create(['logto_id' => 'logto-admin']);
    $admin->assignRole(Role::where('name', 'admin')->where('guard_name', 'logto')->first());

    $this->withHeader('Authorization', 'Bearer '.importToken(JwtTestHelper::claims('logto-admin'), $this->keys))
        ->get('/api/v1/members/imports/template')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
        ->assertHeader('Content-Disposition', 'attachment; filename="member-import-template.csv"')
        ->assertSee('student_id,first_name,last_name,email')
        ->assertSee('BIT-001-23')
        ->assertSee('CSS-002-24');
});

test('admin can view the status of an import', function () {
    Role::create(['name' => 'admin', 'guard_name' => 'logto']);
    $admin = User::factory()->create(['logto_id' => 'logto-admin']);
    $admin->assignRole(Role::where('name', 'admin')->where('guard_name', 'logto')->first());

    $import = MemberImport::create([
        'original_name' => 'import.csv',
        'file_path' => 'members/imports/uuid.csv',
        'status' => MemberImport::STATUS_PROCESSING,
    ]);

    $this->withHeader('Authorization', 'Bearer '.importToken(JwtTestHelper::claims('logto-admin'), $this->keys))
        ->getJson("/api/v1/members/imports/{$import->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $import->id)
        ->assertJsonPath('data.status', MemberImport::STATUS_PROCESSING);
});
