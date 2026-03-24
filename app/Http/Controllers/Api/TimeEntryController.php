<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTimeEntryRequest;
use App\Http\Requests\UpdateTimeEntryRequest;
use App\Http\Resources\TimeEntryResource;
use App\Models\TimeEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TimeEntryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = TimeEntry::with('project');

        // When team=1 is passed with a project_id, return all members' entries
        if ($request->query('team') && $request->query('project_id')) {
            $query->where('project_id', $request->query('project_id'));
        } else {
            $query->where('user_id', Auth::id());
            if ($projectId = $request->query('project_id')) {
                $query->where('project_id', $projectId);
            }
        }

        if ($from = $request->query('from')) {
            $query->whereDate('date', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->whereDate('date', '<=', $to);
        }

        $perPage = min((int) $request->query('per_page', 20), 100);
        $entries = $query->orderByDesc('date')->paginate($perPage);

        return response()->json([
            'time_entries' => TimeEntryResource::collection($entries),
            'meta'         => [
                'current_page' => $entries->currentPage(),
                'last_page'    => $entries->lastPage(),
                'total'        => $entries->total(),
            ],
        ]);
    }

    public function store(StoreTimeEntryRequest $request): JsonResponse
    {
        $entry = TimeEntry::create([
            ...$request->validated(),
            'user_id' => Auth::id(),
        ]);

        $entry->load('project');

        return response()->json([
            'message'    => 'Time entry created.',
            'time_entry' => new TimeEntryResource($entry),
        ], 201);
    }

    public function update(UpdateTimeEntryRequest $request, TimeEntry $timeEntry): JsonResponse
    {
        if ($timeEntry->user_id !== Auth::id()) {
            abort(403, 'Not your time entry.');
        }

        $timeEntry->update($request->validated());

        return response()->json([
            'message'    => 'Time entry updated.',
            'time_entry' => new TimeEntryResource($timeEntry->fresh('project')),
        ]);
    }

    public function destroy(TimeEntry $timeEntry): JsonResponse
    {
        if ($timeEntry->user_id !== Auth::id()) {
            abort(403, 'Not your time entry.');
        }

        $timeEntry->delete();

        return response()->json([
            'message' => 'Time entry deleted.',
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['required', 'date'],
            'to'   => ['required', 'date', 'after_or_equal:from'],
        ]);

        $summary = TimeEntry::where('user_id', Auth::id())
            ->whereDate('date', '>=', $request->from)
            ->whereDate('date', '<=', $request->to)
            ->selectRaw('project_id, SUM(hours) as total_hours, COUNT(*) as entries_count')
            ->groupBy('project_id')
            ->with('project')
            ->get()
            ->map(fn ($row) => [
                'project_id'    => $row->project_id,
                'project_name'  => $row->project?->name,
                'total_hours'   => (float) $row->total_hours,
                'entries_count' => $row->entries_count,
            ]);

        return response()->json([
            'summary'     => $summary,
            'grand_total' => $summary->sum('total_hours'),
        ]);
    }
}
