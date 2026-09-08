<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\Activitylog\Models\Activity;

class ActivityLogService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        return Activity::query()
            ->with(['causer', 'subject'])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $pattern = '%'.mb_strtolower($search).'%';
                $query->where(function (Builder $query) use ($pattern): void {
                    $query->whereRaw('LOWER(description) LIKE ?', [$pattern])
                        ->orWhere(function (Builder $query) use ($pattern): void {
                            $query->whereNotNull('causer_type')
                                ->whereHas('causer', function (Builder $query) use ($pattern): void {
                                    $query->whereRaw('LOWER(first_name) LIKE ?', [$pattern])
                                        ->orWhereRaw('LOWER(last_name) LIKE ?', [$pattern]);
                                });
                        });
                });
            })
            ->when(($filters['event'] ?? '') !== '', function (Builder $query) use ($filters): void {
                $query->where('event', $filters['event']);
            })
            ->when($filters['subject_type'] ?? null, function (Builder $query, string $subjectType): void {
                $query->where('subject_type', $subjectType);
            })
            ->latest()
            ->paginate($filters['per_page'] ?? 15);
    }
}
