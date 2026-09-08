<?php

namespace App\Services;

use App\Enums\AssetStatus;
use App\Models\Asset;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AssetService
{
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = Asset::with('loans')->orderByDesc('created_at');

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function find(int $id): ?Asset
    {
        return Asset::with(['loans.borrower', 'loans.issuedBy'])->find($id);
    }

    public function findOrFail(int $id): Asset
    {
        $asset = $this->find($id);

        if (! $asset) {
            throw new ModelNotFoundException('Asset not found');
        }

        return $asset;
    }

    public function create(array $data): Asset
    {
        return Asset::create([
            'name' => $data['name'],
            'serial_number' => $data['serial_number'] ?? null,
            'category' => $data['category'],
            'status' => AssetStatus::AVAILABLE,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    public function update(Asset $asset, array $data): Asset
    {
        $asset->update([
            'name' => $data['name'] ?? $asset->name,
            'serial_number' => $data['serial_number'] ?? $asset->serial_number,
            'category' => $data['category'] ?? $asset->category,
            'status' => $data['status'] ?? $asset->status,
            'notes' => $data['notes'] ?? $asset->notes,
        ]);

        return $asset;
    }

    public function delete(Asset $asset): void
    {
        $asset->update(['status' => AssetStatus::RETIRED]);
    }

    public function summary(): array
    {
        return Asset::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }

    public function getCategories(): array
    {
        return Asset::distinct()
            ->whereNotNull('category')
            ->pluck('category')
            ->toArray();
    }
}
