<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentCategoryRequest;
use App\Http\Requests\UpdateDocumentCategoryRequest;
use App\Http\Resources\DocumentCategoryResource;
use App\Http\Responses\APIResponse;
use App\Services\DocumentCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentCategoryController extends Controller
{
    use APIResponse;

    public function __construct(
        protected DocumentCategoryService $categoryService,
    ) {
        $this->middleware('role:admin|superadmin,logto')->except('index', 'show');
    }

    public function index(Request $request): JsonResponse
    {
        $categories = $this->categoryService->list();

        return $this->success(
            DocumentCategoryResource::collection($categories),
            'Document categories retrieved successfully'
        );
    }

    public function show(int $id): JsonResponse
    {
        $category = $this->categoryService->find($id);

        if (! $category) {
            return $this->error('Document category not found', JsonResponse::HTTP_NOT_FOUND);
        }

        return $this->success(
            new DocumentCategoryResource($category),
            'Document category retrieved successfully'
        );
    }

    public function store(StoreDocumentCategoryRequest $request): JsonResponse
    {
        $category = $this->categoryService->create($request->validated());

        return $this->success(
            new DocumentCategoryResource($category),
            'Document category created successfully',
            JsonResponse::HTTP_CREATED,
        );
    }

    public function update(UpdateDocumentCategoryRequest $request, int $id): JsonResponse
    {
        $category = $this->categoryService->find($id);

        if (! $category) {
            return $this->error('Document category not found', JsonResponse::HTTP_NOT_FOUND);
        }

        $this->categoryService->update($category, $request->validated());

        return $this->success(
            new DocumentCategoryResource($category->refresh()),
            'Document category updated successfully'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $category = $this->categoryService->find($id);

        if (! $category) {
            return $this->error('Document category not found', JsonResponse::HTTP_NOT_FOUND);
        }

        $this->categoryService->delete($category);

        return $this->success(null, 'Document category deleted successfully');
    }
}
