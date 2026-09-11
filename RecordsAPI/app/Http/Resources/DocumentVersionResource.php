<?php

namespace App\Http\Resources;

use App\Models\DocumentVersion;
use App\Support\DocumentFileUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;

/** @mixin DocumentVersion */
class DocumentVersionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $media = $this->mediaItem();

        return [
            'id' => $this->id,
            'version_number' => $this->version_number,
            'change_summary' => $this->change_summary,
            'file_url' => $this->resolveFileUrl($media),
            'mime_type' => $media?->mime_type,
            'uploaded_by' => $this->uploaded_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    protected function resolveFileUrl($media = null): ?string
    {
        $media ??= $this->mediaItem();

        if (! $media) {
            return null;
        }

        if ($media->disk === 'public') {
            return $media->getUrl();
        }

        return app(DocumentFileUrl::class)->make($this->id);
    }

    protected function mediaItem()
    {
        $media = $this->whenLoaded('media', fn () => $this->getFirstMedia('file'));

        return $media instanceof MissingValue ? $this->getFirstMedia('file') : $media;
    }
}
