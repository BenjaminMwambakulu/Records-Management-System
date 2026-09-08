<?php

namespace App\Http\Resources;

use App\Models\MemberImport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MemberImport */
class MemberImportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'original_name' => $this->original_name,
            'status' => $this->status,
            'total_rows' => $this->total_rows,
            'processed' => $this->processed,
            'created_count' => $this->created_count,
            'duplicate_count' => $this->duplicate_count,
            'failed_count' => $this->failed_count,
            'error_rows' => $this->error_rows,
            'error_message' => $this->error_message,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
