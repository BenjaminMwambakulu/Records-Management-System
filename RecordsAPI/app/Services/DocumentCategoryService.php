<?php

namespace App\Services;

use App\Models\DocumentCategory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class DocumentCategoryService
{
    public function list(): Collection
    {
        $rows = Cache::remember('document-categories:v2', 300, function (): array {
            return DocumentCategory::query()
                ->orderBy('name')
                ->get()
                ->toArray();
        });

        return DocumentCategory::hydrate($rows);
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
        $category = DocumentCategory::create($data);
        Cache::forget('document-categories:v2');

        return $category;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(DocumentCategory $category, array $data): DocumentCategory
    {
        $category->update($data);
        Cache::forget('document-categories:v2');

        return $category;
    }

    public function delete(DocumentCategory $category): void
    {
        $category->delete();
        Cache::forget('document-categories:v2');
    }
}
