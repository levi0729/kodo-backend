<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CalendarEventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = CalendarEvent::with(['organizer', 'attendees']);

        if ($request->filled('team_id') && $request->query('team_id') !== 'undefined') {
            $query->where('team_id', (int) $request->query('team_id'));
        }

        if ($startAfter = $request->query('start_after')) {
            $query->where('start_time', '>=', $startAfter);
        }

        if ($startBefore = $request->query('start_before')) {
            $query->where('start_time', '<=', $startBefore);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $perPage = min((int) $request->query('per_page', 20), 100);
        $events = $query->orderBy('start_time')->paginate($perPage);

        $items = collect($events->items())->map(function ($event) {
            $data = $event->toArray();
            $data['organizer_id'] = $event->organizer_id;
            $data['attendees'] = $event->attendees->pluck('id')->values();
            $data['start_time'] = $event->start_time?->toIso8601String();
            $data['end_time'] = $event->end_time?->toIso8601String();
            return $data;
        });

        return response()->json([
            'calendar_events' => $items,
            'meta'            => [
                'current_page' => $events->currentPage(),
                'last_page'    => $events->lastPage(),
                'per_page'     => $events->perPage(),
                'total'        => $events->total(),
            ],
        ]);
    }

    public function show(CalendarEvent $calendarEvent): JsonResponse
    {
        $calendarEvent->load(['organizer', 'attendees']);

        return response()->json([
            'calendar_event' => $calendarEvent,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'team_id'           => ['nullable', 'integer', 'exists:teams,id'],
            'title'             => ['required', 'string', 'max:200'],
            'description'       => ['nullable', 'string'],
            'location'          => ['nullable', 'string', 'max:200'],
            'is_online_meeting' => ['nullable', 'boolean'],
            'meeting_url'       => ['nullable', 'string', 'max:500'],
            'start_time'        => ['required', 'date'],
            'end_time'          => ['required', 'date', 'after_or_equal:start_time'],
            'is_all_day'        => ['nullable', 'boolean'],
            'recurrence_rule'   => ['nullable', 'string', 'max:255'],
            'status'            => ['nullable', 'string', 'in:confirmed,tentative,cancelled'],
            'reminder_minutes'  => ['nullable', 'integer', 'min:0'],
            'color'             => ['nullable', 'string', 'max:20'],
            'attendees'         => ['nullable', 'array'],
            'attendees.*'       => ['integer', 'exists:users,id'],
        ]);

        $data['organizer_id'] = Auth::id();

        $event = CalendarEvent::create(collect($data)->except('attendees')->toArray());

        if (!empty($data['attendees'])) {
            $event->attendees()->attach($data['attendees']);
        }

        $event->load(['organizer', 'attendees']);

        return response()->json([
            'message'        => 'Calendar event created.',
            'calendar_event' => $event,
        ], 201);
    }

    public function update(Request $request, CalendarEvent $calendarEvent): JsonResponse
    {
        $data = $request->validate([
            'title'             => ['sometimes', 'string', 'max:200'],
            'description'       => ['nullable', 'string'],
            'location'          => ['nullable', 'string', 'max:200'],
            'is_online_meeting' => ['nullable', 'boolean'],
            'meeting_url'       => ['nullable', 'string', 'max:500'],
            'start_time'        => ['sometimes', 'date'],
            'end_time'          => ['sometimes', 'date', 'after_or_equal:start_time'],
            'is_all_day'        => ['nullable', 'boolean'],
            'recurrence_rule'   => ['nullable', 'string', 'max:255'],
            'status'            => ['nullable', 'string', 'in:confirmed,tentative,cancelled'],
            'reminder_minutes'  => ['nullable', 'integer', 'min:0'],
            'color'             => ['nullable', 'string', 'max:20'],
            'attendees'         => ['nullable', 'array'],
            'attendees.*'       => ['integer', 'exists:users,id'],
        ]);

        $calendarEvent->update(collect($data)->except('attendees')->toArray());

        if (array_key_exists('attendees', $data)) {
            $calendarEvent->attendees()->sync($data['attendees'] ?? []);
        }

        return response()->json([
            'message'        => 'Calendar event updated.',
            'calendar_event' => $calendarEvent->fresh(['organizer', 'attendees']),
        ]);
    }

    public function destroy(CalendarEvent $calendarEvent): JsonResponse
    {
        if ($calendarEvent->organizer_id !== Auth::id()) {
            abort(403, 'Only the organizer can delete this event.');
        }

        $calendarEvent->attendees()->detach();
        $calendarEvent->delete();

        return response()->json([
            'message' => 'Calendar event deleted.',
        ]);
    }
}
