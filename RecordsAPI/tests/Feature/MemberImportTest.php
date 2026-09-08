<?php

use App\Jobs\ImportMembersFromFile;
use App\Jobs\SyncMemberToLogto;
use App\Models\MemberImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

test('import job creates valid members and skips duplicates and invalid rows', function () {
    Role::create(['name' => 'member', 'guard_name' => 'logto']);

    User::factory()->create(['email' => 'existing@must.ac.mw', 'student_id' => 'DUP-001']);

    $csv = "student_id,first_name,last_name,email,academic_track,enrolled_year,study_year\n"
        ."GOOD-001,Alice,Smith,alice@must.ac.mw,BIT,2024,2\n"
        ."GOOD-002,Bob,Jones,bob@must.ac.mw,CSS,2023,3\n"
        ."DUP-001,Existing,User,existing@must.ac.mw,BIT,2024,1\n"
        ."BAD,Incomplete,,,,,\n";

    $path = Storage::disk('local')->put('members/imports/test.csv', $csv);

    $import = MemberImport::create([
        'original_name' => 'import.csv',
        'file_path' => 'members/imports/test.csv',
        'status' => MemberImport::STATUS_PENDING,
    ]);

    Queue::fake();

    (new ImportMembersFromFile($import))->handle();

    expect($import->refresh()->status)->toBe(MemberImport::STATUS_COMPLETED)
        ->and($import->total_rows)->toBe(4)
        ->and($import->processed)->toBe(4)
        ->and($import->created_count)->toBe(2)
        ->and($import->duplicate_count)->toBe(1)
        ->and($import->failed_count)->toBe(1)
        ->and($import->error_rows)->toHaveCount(2);

    $alice = User::where('email', 'alice@must.ac.mw')->first();
    $bob = User::where('email', 'bob@must.ac.mw')->first();

    expect($alice)->not->toBeNull()
        ->and($alice->academic_track?->value)->toBe('BIT')
        ->and($alice->enrolled_year)->toBe(2024)
        ->and($alice->study_year)->toBe(2)
        ->and($alice->getRoleNames())->toContain('member')
        ->and($bob)->not->toBeNull()
        ->and($bob->getRoleNames())->toContain('member');

    expect(User::where('email', 'existing@must.ac.mw')->exists())->toBeTrue()
        ->and(User::where('student_id', 'DUP-001')->count())->toBe(1);

    Queue::assertPushed(SyncMemberToLogto::class, 2);
});

test('import job processes excel files', function () {
    Role::create(['name' => 'member', 'guard_name' => 'logto']);

    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([
        ['Student ID', 'First Name', 'Last Name', 'Email', 'Academic Track'],
        ['XLS-001', 'Carol', 'Danvers', 'carol@must.ac.mw', 'BIT'],
        ['XLS-002', 'Peter', 'Parker', 'peter@must.ac.mw', 'CSS'],
    ]);

    $tempPath = tempnam(sys_get_temp_dir(), 'import').'.xlsx';
    (new Xlsx($spreadsheet))->save($tempPath);
    $spreadsheet->disconnectWorksheets();

    $path = Storage::disk('local')->put('members/imports/test.xlsx', file_get_contents($tempPath));

    $import = MemberImport::create([
        'original_name' => 'import.xlsx',
        'file_path' => 'members/imports/test.xlsx',
        'status' => MemberImport::STATUS_PENDING,
    ]);

    Queue::fake();

    (new ImportMembersFromFile($import))->handle();

    expect($import->refresh()->status)->toBe(MemberImport::STATUS_COMPLETED)
        ->and($import->created_count)->toBe(2)
        ->and($import->duplicate_count)->toBe(0)
        ->and($import->failed_count)->toBe(0)
        ->and(User::where('email', 'carol@must.ac.mw')->exists())->toBeTrue()
        ->and(User::where('email', 'peter@must.ac.mw')->exists())->toBeTrue();
});

test('an empty csv file fails the import', function () {
    Storage::disk('local')->put('members/imports/empty.csv', '');

    $import = MemberImport::create([
        'original_name' => 'empty.csv',
        'file_path' => 'members/imports/empty.csv',
        'status' => MemberImport::STATUS_PENDING,
    ]);

    (new ImportMembersFromFile($import))->handle();

    expect($import->refresh()->status)->toBe(MemberImport::STATUS_FAILED)
        ->and($import->error_message)->toContain('empty or missing a header row');
});
