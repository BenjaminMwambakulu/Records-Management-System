<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFinancialCategoryRequest;
use App\Http\Requests\UpdateFinancialCategoryRequest;
use App\Http\Resources\FinancialCategoryResource;
use App\Http\Responses\APIResponse;
use App\Services\FinancialCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialCategoryController extends Controller
{
    use APIResponse;

    public function __construct(
        protected FinancialCategoryService $categoryService,
    ) {
        $this->middleware('permission:financials.update|financials.delete,logto')->except('index', 'show');
    }

    public function index(Request $request): JsonResponse
    {
        $categories = $this->categoryService->list();

        return $this->success(
            FinancialCategoryResource::collection($categories),
            'Financial categories retrieved successfully'
        );
    }

    public function show(int $id): JsonResponse
    {
        $category = $this->categoryService->find($id);

        if (! $category) {
            return $this->error('Financial category not found', JsonResponse::HTTP_NOT_FOUND);
        }

        return $this->success(
            new FinancialCategoryResource($category),
            'Financial category retrieved successfully'
        );
    }

    public function store(StoreFinancialCategoryRequest $request): JsonResponse
    {
        $category = $this->categoryService->create($request->validated());

        return $this->success(
            new FinancialCategoryResource($category),
            'Financial category created successfully',
            JsonResponse::HTTP_CREATED,
        );
    }

    public function update(UpdateFinancialCategoryRequest $request, int $id): JsonResponse
    {
        $category = $this->categoryService->find($id);

        if (! $category) {
            return $this->error('Financial category not found', JsonResponse::HTTP_NOT_FOUND);
        }

        $this->categoryService->update($category, $request->validated());

        return $this->success(
            new FinancialCategoryResource($category->refresh()),
            'Financial category updated successfully'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $category = $this->categoryService->find($id);

        if (! $category) {
            return $this->error('Financial category not found', JsonResponse::HTTP_NOT_FOUND);
        }

        $this->categoryService->delete($category);

        return $this->success(null, 'Financial category deleted successfully');
    }
}
