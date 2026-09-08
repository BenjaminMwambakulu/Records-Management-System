<?php

namespace App\Services;

use App\Models\FinancialCategory;
use Illuminate\Database\Eloquent\Collection;

class FinancialCategoryService
{
    public function list(): Collection
    {
        return FinancialCategory::query()
            ->orderBy('name')
            ->get();
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
        return FinancialCategory::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(FinancialCategory $category, array $data): FinancialCategory
    {
        $category->update($data);

        return $category;
    }

    public function delete(FinancialCategory $category): void
    {
        $category->delete();
    }
}
