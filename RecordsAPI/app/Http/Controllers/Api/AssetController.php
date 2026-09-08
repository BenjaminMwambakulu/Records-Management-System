<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutAssetRequest;
use App\Http\Requests\StoreAssetRequest;
use App\Http\Requests\UpdateAssetRequest;
use App\Http\Resources\AssetLoanResource;
use App\Http\Resources\AssetResource;
use App\Http\Responses\APIResponse;
use App\Models\User;
use App\Services\AssetLoanService;
use App\Services\AssetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    use APIResponse;

    public function __construct(
        protected AssetService $assetService,
        protected AssetLoanService $assetLoanService,
    ) {
        $this->middleware('permission:assets.create|assets.update|assets.delete|assets.checkout|assets.return,logto')
            ->except('index', 'show', 'summary', 'categories');
    }

    public function index(Request $request): JsonResponse
    {
        $assets = $this->assetService->list($request->only(['search', 'category', 'status', 'per_page']));

        return $this->success(
            AssetResource::collection($assets),
            'Assets retrieved successfully'
        );
    }

    public function show(int $id): JsonResponse
    {
        $asset = $this->assetService->findOrFail($id);

        return $this->success(
            new AssetResource($asset),
            'Asset retrieved successfully'
        );
    }

    public function store(StoreAssetRequest $request): JsonResponse
    {
        $asset = $this->assetService->create($request->validated());

        return $this->success(
            new AssetResource($asset->load('loans')),
            'Asset created successfully',
            JsonResponse::HTTP_CREATED,
        );
    }

    public function update(UpdateAssetRequest $request, int $id): JsonResponse
    {
        $asset = $this->assetService->findOrFail($id);

        $this->assetService->update($asset, $request->validated());

        return $this->success(
            new AssetResource($asset->refresh()->load('loans')),
            'Asset updated successfully'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $asset = $this->assetService->findOrFail($id);

        $this->assetService->delete($asset);

        return $this->success(null, 'Asset retired successfully');
    }

    public function summary(): JsonResponse
    {
        $summary = $this->assetService->summary();

        return $this->success($summary, 'Asset summary retrieved successfully');
    }

    public function categories(): JsonResponse
    {
        $categories = $this->assetService->getCategories();

        return $this->success($categories, 'Asset categories retrieved successfully');
    }

    public function checkout(CheckoutAssetRequest $request, int $id): JsonResponse
    {
        $asset = $this->assetService->findOrFail($id);

        $borrower = User::findOrFail($request->validated('borrower_id'));

        try {
            $loan = $this->assetLoanService->checkout(
                $asset,
                $borrower,
                auth()->user(),
                $request->validated()
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->success(
            new AssetLoanResource($loan),
            'Asset checked out successfully',
            JsonResponse::HTTP_CREATED,
        );
    }

    public function return(int $id): JsonResponse
    {
        $asset = $this->assetService->findOrFail($id);

        $loan = $this->assetLoanService->return($asset);

        return $this->success(
            new AssetLoanResource($loan),
            'Asset returned successfully'
        );
    }
}
