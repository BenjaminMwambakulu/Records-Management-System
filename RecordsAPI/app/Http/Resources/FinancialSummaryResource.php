<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \ArrayObject */
class FinancialSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'total_income' => $this['total_income'],
            'total_expense' => $this['total_expense'],
            'balance' => $this['balance'],
        ];
    }
}
