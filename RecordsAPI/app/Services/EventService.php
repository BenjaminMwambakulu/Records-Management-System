<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Jobs\SendRegistrationCancelledJob;
use App\Models\Attendance;
use App\Models\Event;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventService
{
    private const PRIVILEGED_ROLES = ['superadmin', 'admin', 'executive', 'alumni', 'year_rep'];

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
            ->when(! $this->currentUserIsPrivileged(), function (Builder $query): void {
                $query->where('is_published', true);
            })
            ->when(
                $this->currentUserIsPrivileged() && isset($filters['is_published']),
                function (Builder $query) use ($filters): void {
                    $query->where('is_published', filter_var($filters['is_published'], FILTER_VALIDATE_BOOLEAN));
                }
            )
            ->latest()
            ->paginate($filters['per_page'] ?? 15);
    }

    public function find(int $id): ?Event
    {
        $event = Event::with(['creator', 'media'])->find($id);

        if ($event && ! $this->isViewAccessible($event)) {
            return null;
        }

        return $event;
    }

    protected function isViewAccessible(Event $event): bool
    {
        return $event->is_published || $this->currentUserIsPrivileged();
    }

    protected function currentUserIsPrivileged(): bool
    {
        $user = auth('logto')->user();

        return $user !== null && $user->hasAnyRole(self::PRIVILEGED_ROLES, 'logto');
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

    /**
     * @param  array<int, int>  $userIds
     */
    public function cancelRegistrations(Event $event, array $userIds, ?string $reason): int
    {
        return DB::transaction(function () use ($event, $userIds, $reason): int {
            $attendees = $event->attendances()
                ->with('user')
                ->whereIn('user_id', $userIds)
                ->get();

            $attendees->each(function (Attendance $attendance) use ($event, $reason): void {
                $attendance->delete();

                Payment::query()
                    ->where('payable_type', $event->getMorphClass())
                    ->where('payable_id', $event->id)
                    ->where('user_id', $attendance->user_id)
                    ->where('status', PaymentStatus::COMPLETED)
                    ->update(['status' => PaymentStatus::REFUNDED]);

                dispatch(new SendRegistrationCancelledJob(
                    $attendance->user,
                    $event,
                    $reason,
                ));
            });

            activity()
                ->performedOn($event)
                ->causedBy(auth('logto')->user())
                ->withProperties([
                    'cancelled_user_ids' => $attendees->pluck('user_id')->all(),
                    'reason' => $reason,
                ])
                ->log('Bulk registration cancellation');

            return $attendees->count();
        });
    }

    protected function generateUniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $suffix = Event::where('slug', 'like', $base.'%')->count() + 1;

        return $suffix > 1 ? $base.'-'.$suffix : $base;
    }
}
