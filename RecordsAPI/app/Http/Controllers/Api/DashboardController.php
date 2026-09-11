<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\APIResponse;
use App\Models\Asset;
use App\Models\AssetLoan;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Event;
use App\Models\FinancialRecord;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Models\Activity;

class DashboardController extends Controller
{
    use APIResponse;

    private const PRIVILEGED_ROLES = ['superadmin', 'admin', 'executive', 'alumni', 'year_rep'];

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $privileged = $this->currentUserIsPrivileged();
        $cacheKey = 'dashboard:summary:v2:'.$user->id;

        $counts = Cache::remember($cacheKey, 60, function () use ($user, $privileged): array {
            $counts = [];

            if ($user->hasPermissionTo('members.view', 'logto')) {
                $counts['members'] = User::count();
            }

            if ($user->hasPermissionTo('events.view', 'logto')) {
                $counts['events'] = Event::when(
                    ! $privileged,
                    fn ($query) => $query->where('is_published', true)
                )->count();
            }

            if ($user->hasPermissionTo('documents.view', 'logto')) {
                $counts['documents'] = Document::when(
                    ! $privileged,
                    fn ($query) => $query->where('is_public', true)
                )->count();
                $counts['document_categories'] = DocumentCategory::count();
                $counts['recent_documents'] = Document::with('category')
                    ->when(! $privileged, fn ($query) => $query->where('is_public', true))
                    ->latest()
                    ->take(5)
                    ->get()
                    ->toArray();
            }

            if ($user->hasPermissionTo('assets.view', 'logto')) {
                $counts['assets'] = Asset::count();
                $counts['assets_on_loan'] = AssetLoan::whereNull('returned_date')->count();
            }

            if ($user->hasPermissionTo('financials.view', 'logto')) {
                $counts['financial_records'] = FinancialRecord::count();
                $counts['total_income'] = (float) FinancialRecord::where('type', 'income')->sum('amount');
                $counts['total_expense'] = (float) FinancialRecord::where('type', 'expense')->sum('amount');
            }

            if ($user->hasPermissionTo('activity_logs.view', 'logto')) {
                $counts['recent_activity'] = Activity::with('causer')->latest()->take(5)->get()->toArray();
            }

            return $counts;
        });

        return $this->success($counts, 'Dashboard summary retrieved successfully');
    }

    protected function currentUserIsPrivileged(): bool
    {
        $user = auth('logto')->user();

        return $user !== null && $user->hasAnyRole(self::PRIVILEGED_ROLES, 'logto');
    }
}
