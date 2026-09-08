<?php

namespace App\Http\Resources;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Event */
class EventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'location' => $this->location,
            'event_date' => $this->event_date,
            'start_time' => $this->start_time?->format('H:i'),
            'duration' => $this->duration,
            'entry_fee' => $this->entry_fee,
            'budget' => $this->budget,
            'qr_code_hash' => $this->qr_code_hash,
            'is_published' => $this->is_published,
            'cover_url' => $this->getFirstMediaUrl('cover') ?: null,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
