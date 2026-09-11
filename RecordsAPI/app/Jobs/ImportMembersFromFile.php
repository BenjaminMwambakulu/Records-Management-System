<?php

namespace App\Jobs;

use App\Enums\AcademicTrack;
use App\Models\MemberImport;
use App\Models\User;
use App\Services\MemberService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Enum;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class ImportMembersFromFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public MemberImport $import) {}

    /**
     * @return array<int, \Illuminate\Contracts\Queue\ShouldQueue>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('member-import-'.$this->import->id))->dontRelease(),
        ];
    }

    public function handle(): void
    {
        try {
            $this->process();
        } catch (\Throwable $e) {
            $this->markFailed($e);
        }
    }

    protected function process(): void
    {
        $this->import->update(['status' => MemberImport::STATUS_PROCESSING]);

        $filePath = Storage::disk('local')->path($this->import->file_path);
        $rows = $this->readRows($filePath);

        $seenEmails = [];
        $seenStudentIds = [];
        $createdCount = 0;
        $duplicateCount = 0;
        $failedCount = 0;
        $processed = 0;
        $errors = [];

        foreach ($rows as $row) {
            $processed++;
            $rowNumber = $row['number'];
            $data = $row['data'];

            $data['enrolled_year'] = isset($data['enrolled_year']) && is_numeric($data['enrolled_year'])
                ? (int) $data['enrolled_year']
                : $data['enrolled_year'] ?? null;
            $data['study_year'] = isset($data['study_year']) && is_numeric($data['study_year'])
                ? (int) $data['study_year']
                : $data['study_year'] ?? null;

            $validator = Validator::make($data, [
                'student_id' => ['required', 'string', 'max:255'],
                'first_name' => ['required', 'string', 'max:255'],
                'last_name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255'],
                'academic_track' => ['nullable', new Enum(AcademicTrack::class)],
                'enrolled_year' => ['nullable', 'integer', 'min:1950', 'max:2200'],
                'study_year' => ['nullable', 'integer', 'min:1', 'max:4'],
            ]);

            if ($validator->fails()) {
                $failedCount++;
                $errors[] = [
                    'row' => $rowNumber,
                    'student_id' => $data['student_id'] ?? '',
                    'reason' => $validator->errors()->first(),
                ];
                $this->persistProgress($processed, $createdCount, $duplicateCount, $failedCount, $errors);

                continue;
            }

            $email = strtolower(trim($data['email']));
            $studentId = trim($data['student_id']);

            if (in_array($email, $seenEmails, true) || User::where('email', $email)->exists()) {
                $duplicateCount++;
                $errors[] = ['row' => $rowNumber, 'student_id' => $studentId, 'reason' => 'Duplicate email'];
                $this->persistProgress($processed, $createdCount, $duplicateCount, $failedCount, $errors);

                continue;
            }

            if (in_array($studentId, $seenStudentIds, true) || User::where('student_id', $studentId)->exists()) {
                $duplicateCount++;
                $errors[] = ['row' => $rowNumber, 'student_id' => $studentId, 'reason' => 'Duplicate student ID'];
                $this->persistProgress($processed, $createdCount, $duplicateCount, $failedCount, $errors);

                continue;
            }

            $seenEmails[] = $email;
            $seenStudentIds[] = $studentId;

            try {
                app(MemberService::class)->create([
                    'student_id' => $studentId,
                    'first_name' => trim($data['first_name']),
                    'last_name' => trim($data['last_name']),
                    'email' => $email,
                    'academic_track' => $data['academic_track'] ?? null,
                    'enrolled_year' => $data['enrolled_year'] ?? null,
                    'study_year' => $data['study_year'] ?? null,
                    'skills' => [],
                ], ['member']);

                $createdCount++;
            } catch (\Throwable $e) {
                $failedCount++;
                $errors[] = ['row' => $rowNumber, 'student_id' => $studentId, 'reason' => $e->getMessage()];
                Log::warning('Import row failed', [
                    'import_id' => $this->import->id,
                    'row' => $rowNumber,
                    'error' => $e->getMessage(),
                ]);
            }

            $this->persistProgress($processed, $createdCount, $duplicateCount, $failedCount, $errors);
        }

        $this->import->update([
            'status' => MemberImport::STATUS_COMPLETED,
            'total_rows' => $this->import->total_rows ?? $processed,
            'processed' => $processed,
            'created_count' => $createdCount,
            'duplicate_count' => $duplicateCount,
            'failed_count' => $failedCount,
            'error_rows' => array_slice($errors, -50),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        $this->markFailed($e);
    }

    protected function markFailed(\Throwable $e): void
    {
        $this->import->update([
            'status' => MemberImport::STATUS_FAILED,
            'error_message' => Str::limit($e->getMessage(), 1000),
        ]);

        Log::error('Member import job failed', [
            'import_id' => $this->import->id,
            'error' => $e->getMessage(),
        ]);
    }

    private function persistProgress(int $processed, int $created, int $duplicate, int $failed, array $errors): void
    {
        $this->import->update([
            'processed' => $processed,
            'created_count' => $created,
            'duplicate_count' => $duplicate,
            'failed_count' => $failed,
            'error_rows' => array_slice($errors, -50),
        ]);
    }

    private function readRows(string $filePath): iterable
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($extension === 'csv') {
            yield from $this->readCsv($filePath);

            return;
        }

        yield from $this->readExcel($filePath);
    }

    private function readCsv(string $filePath): iterable
    {
        $handle = fopen($filePath, 'r');

        if (! $handle) {
            throw new RuntimeException("Could not open CSV file: {$filePath}");
        }

        $headers = null;
        $map = [];
        $lineNumber = 0;

        try {
            while (($row = fgetcsv($handle)) !== false) {
                $lineNumber++;

                if ($row === [null] || $row === []) {
                    continue;
                }

                if ($headers === null) {
                    $headers = array_map('trim', $row);
                    $map = $this->mapHeaders($headers);

                    continue;
                }

                $data = [];
                foreach ($map as $index => $key) {
                    if ($key !== null && isset($row[$index]) && $row[$index] !== '') {
                        $data[$key] = trim($row[$index]);
                    }
                }

                if ($data !== []) {
                    yield ['number' => $lineNumber, 'data' => $data];
                }
            }
        } finally {
            fclose($handle);
        }

        if ($headers === null) {
            throw new RuntimeException('CSV file is empty or missing a header row.');
        }
    }

    private function readExcel(string $filePath): iterable
    {
        try {
            $reader = IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($filePath);
        } catch (\Throwable $e) {
            throw new RuntimeException('Could not read Excel file: '.$e->getMessage());
        }

        try {
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();
        } finally {
            $spreadsheet->disconnectWorksheets();
        }

        if (count($rows) < 2) {
            throw new RuntimeException('Excel file is empty or missing a header row.');
        }

        $headers = array_map('trim', array_shift($rows));
        $map = $this->mapHeaders($headers);
        $lineNumber = 1;

        foreach ($rows as $row) {
            $lineNumber++;
            $data = [];

            foreach ($map as $index => $key) {
                if ($key !== null && isset($row[$index]) && $row[$index] !== null && $row[$index] !== '') {
                    $data[$key] = trim((string) $row[$index]);
                }
            }

            if ($data !== []) {
                yield ['number' => $lineNumber, 'data' => $data];
            }
        }
    }

    private function mapHeaders(array $headers): array
    {
        $aliasMap = [
            'studentid' => 'student_id',
            'studentno' => 'student_id',
            'studentnumber' => 'student_id',
            'firstname' => 'first_name',
            'lastname' => 'last_name',
            'email' => 'email',
            'emailaddress' => 'email',
            'academictrack' => 'academic_track',
            'track' => 'academic_track',
            'enrolledyear' => 'enrolled_year',
            'enrollyear' => 'enrolled_year',
            'studyyear' => 'study_year',
            'yearofstudy' => 'study_year',
            'year' => 'study_year',
        ];

        return array_map(function (string $header) use ($aliasMap): ?string {
            $key = strtolower(preg_replace('/[^a-z0-9]/i', '', $header));

            return $aliasMap[$key] ?? null;
        }, $headers);
    }
}
