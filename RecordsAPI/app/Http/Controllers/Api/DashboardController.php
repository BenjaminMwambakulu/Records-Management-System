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
use Spatie\Activitylog\Models\Activity;

class DashboardController extends Controller
{
    use APIResponse;

    public function summary(): JsonResponse
    {
        $counts = [
            'members' => User::count(),
            'events' => Event::count(),
            'documents' => Document::count(),
            'document_categories' => DocumentCategory::count(),
            'assets' => Asset::count(),
            'assets_on_loan' => AssetLoan::whereNull('returned_date')->count(),
            'financial_records' => FinancialRecord::count(),
            'total_income' => (float) FinancialRecord::where('type', 'income')->sum('amount'),
            'total_expense' => (float) FinancialRecord::where('type', 'expense')->sum('amount'),
            'recent_activity' => Activity::with('causer')->latest()->take(5)->get(),
            'recent_documents' => Document::with('category')->latest()->take(5)->get(),
        ];

        return $this->success($counts, 'Dashboard summary retrieved successfully');
    }
}
