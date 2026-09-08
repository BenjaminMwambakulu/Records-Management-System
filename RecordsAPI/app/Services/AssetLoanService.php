<?php

namespace App\Services;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetLoan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AssetLoanService
{
    public function __construct(
        protected AssetService $assetService,
    ) {}

    public function checkout(Asset $asset, User $borrower, User $issuedBy, array $data): AssetLoan
    {
        if ($asset->status !== AssetStatus::AVAILABLE) {
            throw new \InvalidArgumentException('Asset is not available for checkout');
        }

        $loan = AssetLoan::create([
            'asset_id' => $asset->id,
            'borrower_id' => $borrower->id,
            'issued_by' => $issuedBy->id,
            'checkout_date' => Carbon::now(),
            'due_date' => $data['due_date'],
        ]);

        $asset->update(['status' => AssetStatus::BORROWED]);

        return $loan->load(['borrower', 'issuedBy']);
    }

    public function return(Asset $asset): AssetLoan
    {
        $activeLoan = $asset->loans()
            ->whereNull('returned_date')
            ->latest('checkout_date')
            ->first();

        if (! $activeLoan) {
            throw new ModelNotFoundException('No active loan found for this asset');
        }

        $activeLoan->update(['returned_date' => Carbon::now()]);
        $asset->update(['status' => AssetStatus::AVAILABLE]);

        return $activeLoan->load(['borrower', 'issuedBy']);
    }

    public function listByAsset(Asset $asset, array $filters = []): LengthAwarePaginator
    {
        return $asset->loans()
            ->with(['borrower', 'issuedBy'])
            ->orderByDesc('checkout_date')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function listOverdue(array $filters = []): LengthAwarePaginator
    {
        return AssetLoan::with(['asset', 'borrower', 'issuedBy'])
            ->whereNull('returned_date')
            ->where('due_date', '<', Carbon::now())
            ->orderBy('due_date')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function findOrFail(int $id): AssetLoan
    {
        $loan = AssetLoan::with(['asset', 'borrower', 'issuedBy'])->find($id);

        if (! $loan) {
            throw new ModelNotFoundException('Asset loan not found');
        }

        return $loan;
    }
}
