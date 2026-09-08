<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AssetLoanResource;
use App\Http\Responses\APIResponse;
use App\Models\Asset;
use App\Services\AssetLoanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetLoanController extends Controller
{
    use APIResponse;

    public function __construct(
        protected AssetLoanService $assetLoanService,
    ) {
        $this->middleware('permission:assets.checkout|assets.return,logto');
    }

    public function index(Request $request, int $assetId): JsonResponse
    {
        $asset = Asset::findOrFail($assetId);

        $loans = $this->assetLoanService->listByAsset(
            $asset,
            $request->only(['per_page'])
        );

        return $this->success(
            AssetLoanResource::collection($loans),
            'Asset loans retrieved successfully'
        );
    }

    public function overdue(Request $request): JsonResponse
    {
        $loans = $this->assetLoanService->listOverdue(
            $request->only(['per_page'])
        );

        return $this->success(
            AssetLoanResource::collection($loans),
            'Overdue loans retrieved successfully'
        );
    }
}
