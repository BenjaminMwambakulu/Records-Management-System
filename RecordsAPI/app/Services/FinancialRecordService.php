<?php

namespace App\Services;

use App\Models\FinancialRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class FinancialRecordService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        return FinancialRecord::query()
            ->with(['category', 'recordedBy'])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where('title', 'ilike', "%{$search}%");
            })
            ->when($filters['type'] ?? null, function (Builder $query, string $type): void {
                $query->where('type', $type);
            })
            ->when($filters['category_id'] ?? null, function (Builder $query, $categoryId): void {
                $query->where('category_id', $categoryId);
            })
            ->latest('transaction_date')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function find(int $id): ?FinancialRecord
    {
        return FinancialRecord::with(['category', 'recordedBy'])->find($id);
    }

    /**
     * @return array{total_income: string, total_expense: string, balance: string}
     */
    public function summary(): array
    {
        $income = FinancialRecord::query()->where('type', 'income')->sum('amount');
        $expense = FinancialRecord::query()->where('type', 'expense')->sum('amount');

        return [
            'total_income' => number_format((float) $income, 2, '.', ''),
            'total_expense' => number_format((float) $expense, 2, '.', ''),
            'balance' => number_format((float) $income - (float) $expense, 2, '.', ''),
        ];
    }

    /**
     * Same filters as list() but unpaginated, ordered by date. Used by export.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, FinancialRecord>
     */
    public function filteredForExport(array $filters = []): Collection
    {
        return FinancialRecord::query()
            ->with(['category', 'recordedBy'])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where('title', 'ilike', "%{$search}%");
            })
            ->when($filters['type'] ?? null, function (Builder $query, string $type): void {
                $query->where('type', $type);
            })
            ->when($filters['category_id'] ?? null, function (Builder $query, $categoryId): void {
                $query->where('category_id', $categoryId);
            })
            ->latest('transaction_date')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): FinancialRecord
    {
        return FinancialRecord::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(FinancialRecord $record, array $data): FinancialRecord
    {
        $record->update($data);

        return $record;
    }

    public function delete(FinancialRecord $record): void
    {
        $record->delete();
    }

    /**
     * @param  Collection<int, FinancialRecord>  $records
     * @return resource
     */
    public function csvStream($records)
    {
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, ['Title', 'Category', 'Type', 'Amount', 'Transaction Date', 'Recorded By'], ',', '"', '\\');

        foreach ($records as $record) {
            fputcsv($handle, [
                $record->title,
                $record->category?->name ?? '',
                $record->type->value,
                $record->amount,
                $record->transaction_date?->toDateString() ?? '',
                $record->recordedBy?->name ?? '',
            ], ',', '"', '\\');
        }

        rewind($handle);

        return $handle;
    }
}
