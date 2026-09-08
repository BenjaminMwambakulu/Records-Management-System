<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFinancialRecordRequest;
use App\Http\Requests\UpdateFinancialRecordRequest;
use App\Http\Resources\FinancialRecordResource;
use App\Http\Resources\FinancialSummaryResource;
use App\Http\Responses\APIResponse;
use App\Services\FinancialRecordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceController extends Controller
{
    use APIResponse;

    public function __construct(
        protected FinancialRecordService $recordService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $records = $this->recordService->list($request->only(['search', 'type', 'category_id', 'per_page']));

        return $this->success(
            FinancialRecordResource::collection($records),
            'Financial records retrieved successfully'
        );
    }

    public function show(int $id): JsonResponse
    {
        $record = $this->recordService->find($id);

        if (! $record) {
            return $this->error('Financial record not found', JsonResponse::HTTP_NOT_FOUND);
        }

        return $this->success(
            new FinancialRecordResource($record),
            'Financial record retrieved successfully'
        );
    }

    public function store(StoreFinancialRecordRequest $request): JsonResponse
    {
        $record = $this->recordService->create([
            ...$request->validated(),
            'recorded_by' => auth('logto')->id(),
        ]);

        return $this->success(
            new FinancialRecordResource($record->load(['category', 'recordedBy'])),
            'Financial record created successfully',
            JsonResponse::HTTP_CREATED,
        );
    }

    public function update(UpdateFinancialRecordRequest $request, int $id): JsonResponse
    {
        $record = $this->recordService->find($id);

        if (! $record) {
            return $this->error('Financial record not found', JsonResponse::HTTP_NOT_FOUND);
        }

        $this->recordService->update($record, $request->validated());

        return $this->success(
            new FinancialRecordResource($record->refresh()->load(['category', 'recordedBy'])),
            'Financial record updated successfully'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $record = $this->recordService->find($id);

        if (! $record) {
            return $this->error('Financial record not found', JsonResponse::HTTP_NOT_FOUND);
        }

        $this->recordService->delete($record);

        return $this->success(null, 'Financial record deleted successfully');
    }

    public function summary(Request $request): JsonResponse
    {
        return $this->success(
            new FinancialSummaryResource($this->recordService->summary()),
            'Financial summary retrieved successfully'
        );
    }

    public function export(Request $request): StreamedResponse
    {
        $records = $this->recordService->filteredForExport(
            $request->only(['search', 'type', 'category_id'])
        );

        $stream = $this->recordService->csvStream($records);

        return response()->streamDownload(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 'financial-records.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
