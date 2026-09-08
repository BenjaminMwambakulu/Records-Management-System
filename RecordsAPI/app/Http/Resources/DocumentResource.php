<?php

namespace App\Http\Resources;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Document */
class DocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'category' => $this->category ? new DocumentCategoryResource($this->category) : null,
            'status' => $this->status,
            'is_public' => $this->is_public,
            'latest_version' => $this->latestVersion ? new DocumentVersionResource($this->latestVersion) : null,
            'versions' => DocumentVersionResource::collection($this->whenLoaded('versions')),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
