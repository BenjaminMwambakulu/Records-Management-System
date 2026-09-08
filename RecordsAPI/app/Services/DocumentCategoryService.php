<?php

namespace App\Services;

use App\Models\DocumentCategory;
use Illuminate\Database\Eloquent\Collection;

class DocumentCategoryService
{
    public function list(): Collection
    {
        return DocumentCategory::query()
            ->orderBy('name')
            ->get();
    }

    public function find(int $id): ?DocumentCategory
    {
        return DocumentCategory::find($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): DocumentCategory
    {
        return DocumentCategory::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(DocumentCategory $category, array $data): DocumentCategory
    {
        $category->update($data);

        return $category;
    }

    public function delete(DocumentCategory $category): void
    {
        $category->delete();
    }
}
