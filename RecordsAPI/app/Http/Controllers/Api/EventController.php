<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Http\Requests\UploadEventCoverRequest;
use App\Http\Resources\EventAttendanceResource;
use App\Http\Resources\EventResource;
use App\Http\Responses\APIResponse;
use App\Services\EventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    use APIResponse;

    public function __construct(
        protected EventService $eventService,
    ) {
        $this->middleware('permission:events.create|events.update|events.delete|events.checkin,logto')->except(
            'index', 'show', 'attendances', 'register', 'checkRegistration', 'cancelRegistration',
        );
    }

    public function index(Request $request): JsonResponse
    {
        $events = $this->eventService->list($request->only(['search', 'is_published', 'per_page']));

        return $this->success(
            EventResource::collection($events),
            'Events retrieved successfully'
        );
    }

    public function show(int $id): JsonResponse
    {
        $event = $this->eventService->find($id);

        if (! $event) {
            return $this->error('Event not found', JsonResponse::HTTP_NOT_FOUND);
        }

        return $this->success(
            new EventResource($event),
            'Event retrieved successfully'
        );
    }

    public function store(StoreEventRequest $request): JsonResponse
    {
        $event = $this->eventService->create($request->validated());

        return $this->success(
            new EventResource($event),
            'Event created successfully',
            JsonResponse::HTTP_CREATED,
        );
    }

    public function update(UpdateEventRequest $request, int $id): JsonResponse
    {
        $event = $this->eventService->find($id);

        if (! $event) {
            return $this->error('Event not found', JsonResponse::HTTP_NOT_FOUND);
        }

        $this->eventService->update($event, $request->validated());

        return $this->success(
            new EventResource($event->refresh()),
            'Event updated successfully'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $event = $this->eventService->find($id);

        if (! $event) {
            return $this->error('Event not found', JsonResponse::HTTP_NOT_FOUND);
        }

        $this->eventService->delete($event);

        return $this->success(null, 'Event deleted successfully');
    }

    public function attendances(Request $request, int $id): JsonResponse
    {
        $event = $this->eventService->find($id);

        if (! $event) {
            return $this->error('Event not found', JsonResponse::HTTP_NOT_FOUND);
        }

        $attendances = $this->eventService->attendances(
            $event,
            $request->only(['per_page']),
        );

        return $this->success(
            EventAttendanceResource::collection($attendances),
            'Event attendances retrieved successfully'
        );
    }

    public function register(int $id): JsonResponse
    {
        $event = $this->eventService->find($id);

        if (! $event) {
            return $this->error('Event not found', JsonResponse::HTTP_NOT_FOUND);
        }

        if ($event->event_date->isPast()) {
            return $this->error('Registration is closed — this event has already passed.', JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($this->eventService->isRegistered($event)) {
            return $this->error('Already registered for this event', JsonResponse::HTTP_CONFLICT);
        }

        $this->eventService->register($event);

        return $this->success(null, 'Registered successfully');
    }

    public function checkRegistration(int $id): JsonResponse
    {
        $event = $this->eventService->find($id);

        if (! $event) {
            return $this->error('Event not found', JsonResponse::HTTP_NOT_FOUND);
        }

        return $this->success(
            ['registered' => $this->eventService->isRegistered($event)],
            'Registration status retrieved'
        );
    }

    public function cancelRegistration(int $id): JsonResponse
    {
        $event = $this->eventService->find($id);

        if (! $event) {
            return $this->error('Event not found', JsonResponse::HTTP_NOT_FOUND);
        }

        $this->eventService->cancelRegistration($event);

        return $this->success(null, 'Registration cancelled');
    }

    public function uploadCover(UploadEventCoverRequest $request, int $id): JsonResponse
    {
        $event = $this->eventService->find($id);

        if (! $event) {
            return $this->error('Event not found', JsonResponse::HTTP_NOT_FOUND);
        }

        $this->eventService->uploadCover($event, $request->file('cover'));

        return $this->success(
            new EventResource($event->refresh()),
            'Event cover uploaded successfully'
        );
    }

    public function removeCover(int $id): JsonResponse
    {
        $event = $this->eventService->find($id);

        if (! $event) {
            return $this->error('Event not found', JsonResponse::HTTP_NOT_FOUND);
        }

        $this->eventService->removeCover($event);

        return $this->success(
            new EventResource($event->refresh()),
            'Event cover removed successfully'
        );
    }
}
