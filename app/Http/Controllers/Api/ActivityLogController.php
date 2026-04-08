<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use App\Models\Participant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActivityLogController extends Controller
{
    /**
     * List activity logs for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ActivityLog::with('user')
            ->where('user_id', Auth::id());

        if ($action = $request->query('action')) {
            $query->where('action', $action);
        }

        if ($targetType = $request->query('target_type')) {
            $query->where('target_type', $targetType);
        }

        if ($from = $request->query('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $logs = $query->orderByDesc('created_at')
            ->paginate(min((int) $request->query('per_page', 30), 100));

        return response()->json([
            'activity_logs' => ActivityLogResource::collection($logs),
            'meta'          => [
                'current_page' => $logs->currentPage(),
                'last_page'    => $logs->lastPage(),
                'per_page'     => $logs->perPage(),
                'total'        => $logs->total(),
            ],
        ]);
    }

    /**
     * List all activity logs for a specific project.
     */
    public function forProject(int $projectId, Request $request): JsonResponse
    {
        // Verify the user is a member of this project
        $isMember = Participant::where('entity_type', 'project')
            ->where('entity_id', $projectId)
            ->where('user_id', Auth::id())
            ->exists();

        if (! $isMember) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $logs = ActivityLog::with('user')
            ->where('target_type', 'project')
            ->where('target_id', $projectId)
            ->orderByDesc('created_at')
            ->paginate(min((int) $request->query('per_page', 30), 100));

        return response()->json([
            'activity_logs' => ActivityLogResource::collection($logs),
            'meta'          => [
                'current_page' => $logs->currentPage(),
                'last_page'    => $logs->lastPage(),
                'total'        => $logs->total(),
            ],
        ]);
    }

    /**
     * Feed — recent activity scoped to the user's teams and projects.
     */
    public function feed(Request $request): JsonResponse
    {
        $userId = Auth::id();

        // Get the user's project and team IDs
        $projectIds = Participant::where('entity_type', 'project')
            ->where('user_id', $userId)->pluck('entity_id');
        $teamIds = Participant::where('entity_type', 'team')
            ->where('user_id', $userId)->pluck('entity_id');

        $perPage = min((int) $request->query('per_page', 50), 100);

        $logs = ActivityLog::with('user')
            ->where(function ($q) use ($userId, $projectIds, $teamIds) {
                $q->where('user_id', $userId)
                  ->orWhere(function ($q2) use ($projectIds) {
                      $q2->where('target_type', 'project')
                         ->whereIn('target_id', $projectIds);
                  })
                  ->orWhere(function ($q2) use ($teamIds) {
                      $q2->where('target_type', 'team')
                         ->whereIn('target_id', $teamIds);
                  });
            })
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'feed' => ActivityLogResource::collection($logs),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page'    => $logs->lastPage(),
                'total'        => $logs->total(),
            ],
        ]);
    }
}
