<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
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
            ->paginate($request->query('per_page', 30));

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
        $logs = ActivityLog::with('user')
            ->where('target_type', 'project')
            ->where('target_id', $projectId)
            ->orderByDesc('created_at')
            ->paginate($request->query('per_page', 30));

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
     * Global feed — recent activity across all users (for dashboards).
     */
    public function feed(Request $request): JsonResponse
    {
        $logs = ActivityLog::with('user')
            ->orderByDesc('created_at')
            ->paginate($request->query('per_page', 50));

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
