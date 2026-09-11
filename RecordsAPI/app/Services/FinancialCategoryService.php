<?php

namespace App\Services;

use App\Models\FinancialCategory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class FinancialCategoryService
{
    public function list(): Collection
    {
        $rows = Cache::remember('financial-categories:v2', 300, function (): array {
            return FinancialCategory::query()
                ->orderBy('name')
                ->get()
                ->toArray();
        });

        return FinancialCategory::hydrate($rows);
    }

    public function find(int $id): ?FinancialCategory
    {
        return FinancialCategory::find($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): FinancialCategory
    {
        $category = FinancialCategory::create($data);
        Cache::forget('financial-categories:v2');

        return $category;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(FinancialCategory $category, array $data): FinancialCategory
    {
        $category->update($data);
        Cache::forget('financial-categories:v2');

        return $category;
    }

    public function delete(FinancialCategory $category): void
    {
        $category->delete();
        Cache::forget('financial-categories:v2');
    }
}
