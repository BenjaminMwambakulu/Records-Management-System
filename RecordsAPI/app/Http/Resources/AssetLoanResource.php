<?php

namespace App\Http\Resources;

use App\Models\AssetLoan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AssetLoan */
class AssetLoanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'asset_id' => $this->asset_id,
            'asset' => new AssetResource($this->whenLoaded('asset')),
            'borrower' => new MemberResource($this->whenLoaded('borrower')),
            'issued_by' => new MemberResource($this->whenLoaded('issuedBy')),
            'checkout_date' => $this->checkout_date,
            'due_date' => $this->due_date,
            'returned_date' => $this->returned_date,
            'is_overdue' => ! $this->returned_date && $this->due_date->isPast(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
