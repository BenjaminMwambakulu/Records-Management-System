<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMemberImportRequest;
use App\Http\Resources\MemberImportResource;
use App\Http\Responses\APIResponse;
use App\Jobs\ImportMembersFromFile;
use App\Models\MemberImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class MemberImportController extends Controller
{
    use APIResponse;

    public function __construct()
    {
        $this->middleware('role:admin|superadmin,logto');
    }

    public function store(StoreMemberImportRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        $filename = Str::uuid().'.'.$extension;
        $path = $file->storeAs('members/imports', $filename, 'local');

        $import = MemberImport::create([
            'user_id' => Auth::id(),
            'original_name' => $file->getClientOriginalName(),
            'file_path' => $path,
        ]);

        ImportMembersFromFile::dispatch($import);

        return $this->success(
            new MemberImportResource($import->refresh()),
            'Import queued successfully',
            JsonResponse::HTTP_ACCEPTED,
        );
    }

    public function template(): Response
    {
        $headers = ['student_id', 'first_name', 'last_name', 'email', 'academic_track', 'enrolled_year', 'study_year'];

        $rows = [
            ['BIT-001-23', 'Alice', 'Smith', 'alice.smith@must.ac.mw', 'BIT', '2023', '3'],
            ['CSS-002-24', 'Bob', 'Jones', 'bob.jones@must.ac.mw', 'CSS', '2024', '2'],
        ];

        $csv = $this->toCsv([$headers, ...$rows]);

        return response($csv, JsonResponse::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="member-import-template.csv"',
        ]);
    }

    public function show(MemberImport $import): JsonResponse
    {
        return $this->success(
            new MemberImportResource($import),
            'Import retrieved successfully',
        );
    }

    private function toCsv(array $rows): string
    {
        $stream = fopen('php://temp', 'r+');

        fwrite($stream, "\xEF\xBB\xBF");

        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }

        rewind($stream);

        return stream_get_contents($stream);
    }
}
