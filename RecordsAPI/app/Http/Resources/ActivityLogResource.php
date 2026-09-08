<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Activitylog\Models\Activity;

/** @mixin Activity */
class ActivityLogResource extends JsonResource
{
    private static function shortModelName(?string $type): string
    {
        if (!$type) return '';
        $parts = explode('\\', $type);
        return end($parts);
    }

    private static function resolveSubjectName($subject): string
    {
        if ($subject->title ?? null) return $subject->title;
        if ($subject->full_name ?? null) return $subject->full_name;
        if ($subject->name ?? null) return $subject->name;
        if ($subject->first_name ?? null) {
            $lastName = $subject->last_name ?? '';
            return trim("{$subject->first_name} {$lastName}");
        }
        return self::shortModelName(get_class($subject)) . ' #' . $subject->getKey();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $shortType = self::shortModelName($this->subject_type);

        return [
            'id' => $this->id,
            'log_name' => $this->log_name,
            'description' => $this->description,
            'event' => $this->event,
            'causer' => $this->causer ? [
                'id' => $this->causer->getKey(),
                'full_name' => trim("{$this->causer->first_name} {$this->causer->last_name}"),
            ] : null,
            'subject' => $this->subject ? [
                'id' => $this->subject->getKey(),
                'type' => $this->subject_type,
                'type_short' => $shortType,
                'name' => self::resolveSubjectName($this->subject),
            ] : [
                'id' => null,
                'type' => $this->subject_type,
                'type_short' => $shortType,
                'name' => $shortType,
            ],
            'changes' => $this->attribute_changes,
            'created_at' => $this->created_at,
        ];
    }
}
