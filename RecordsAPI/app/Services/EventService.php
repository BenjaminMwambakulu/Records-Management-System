<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Event;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class EventService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        return Event::query()
            ->with(['creator', 'media'])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('title', 'ilike', "%{$search}%")
                        ->orWhere('location', 'ilike', "%{$search}%");
                });
            })
            ->when(isset($filters['is_published']), function (Builder $query) use ($filters): void {
                $query->where('is_published', filter_var($filters['is_published'], FILTER_VALIDATE_BOOLEAN));
            })
            ->latest()
            ->paginate($filters['per_page'] ?? 15);
    }

    public function find(int $id): ?Event
    {
        return Event::with(['creator', 'media'])->find($id);
    }

    /**
     * @return LengthAwarePaginator<int, Attendance>
     */
    public function attendances(Event $event, array $filters = []): LengthAwarePaginator
    {
        return $event->attendances()
            ->with('user')
            ->latest('checked_in_at')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function uploadCover(Event $event, UploadedFile $cover): Event
    {
        $event->addMedia($cover)
            ->toMediaCollection('cover');

        return $event;
    }

    public function removeCover(Event $event): Event
    {
        $event->clearMediaCollection('cover');

        return $event;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Event
    {
        $data['slug'] = $data['slug'] ?? $this->generateUniqueSlug($data['title']);
        $data['qr_code_hash'] = $data['qr_code_hash'] ?? Str::random(32);
        $data['created_by'] = auth('logto')->id();

        return Event::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Event $event, array $data): Event
    {
        $event->update($data);

        return $event;
    }

    public function delete(Event $event): void
    {
        $event->delete();
    }

    public function register(Event $event): Attendance
    {
        $user = auth('logto')->user();

        return $event->attendances()->create([
            'user_id' => $user->id,
        ]);
    }

    public function isRegistered(Event $event): bool
    {
        $user = auth('logto')->user();

        return $event->attendances()->where('user_id', $user->id)->exists();
    }

    public function cancelRegistration(Event $event): bool
    {
        $user = auth('logto')->user();

        return (bool) $event->attendances()->where('user_id', $user->id)->delete();
    }

    protected function generateUniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $suffix = 2;

        while (Event::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
