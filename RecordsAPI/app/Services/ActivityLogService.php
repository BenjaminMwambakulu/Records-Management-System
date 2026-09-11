<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Facades\DB;

class ActivityLogService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $driver = DB::connection()->getDriverName();
        $operator = $driver === 'pgsql' ? 'ilike' : 'like';

        return Activity::query()
            ->with(['causer', 'subject'])
            ->when($filters['search'] ?? null, function (Builder $query, string $search) use ($operator): void {
                $pattern = '%'.$search.'%';
                $query->where(function (Builder $query) use ($pattern, $operator): void {
                    $query->where('description', $operator, $pattern)
                        ->orWhere(function (Builder $query) use ($pattern, $operator): void {
                            $query->whereNotNull('causer_type')
                                ->whereHas('causer', function (Builder $query) use ($pattern, $operator): void {
                                    $query->where('first_name', $operator, $pattern)
                                        ->orWhere('last_name', $operator, $pattern);
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
