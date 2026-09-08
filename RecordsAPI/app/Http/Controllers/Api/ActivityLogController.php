<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogResource;
use App\Http\Responses\APIResponse;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    use APIResponse;

    public function __construct(
        protected ActivityLogService $activityLogService,
    ) {
        $this->middleware('role:admin|superadmin,logto');
    }

    public function index(Request $request): JsonResponse
    {
        $logs = $this->activityLogService->list(
            $request->only(['search', 'event', 'subject_type', 'per_page'])
        );

        return $this->success(
            ActivityLogResource::collection($logs),
            'Activity logs retrieved successfully'
        );
    }
}
