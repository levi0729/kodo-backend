<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogResource;
use App\Http\Resources\ProjectResource;
use App\Models\ActivityLog;
use App\Models\Friend;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $userId = Auth::id();

        $projectsCount = Project::where('owner_id', $userId)->count();

        $recentProjects = Project::where('owner_id', $userId)
            ->with('owner')
            ->withCount(['tasks', 'teams'])
            ->orderByDesc('updated_at')
            ->take(5)
            ->get();

        $tasksByStatus = Task::whereHas('project', fn ($q) => $q->where('owner_id', $userId))
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $hoursThisWeek = TimeEntry::where('user_id', $userId)
            ->whereDate('date', '>=', now()->startOfWeek())
            ->whereDate('date', '<=', now()->endOfWeek())
            ->sum('hours');

        $friendsCount = Friend::where(function ($q) use ($userId) {
                $q->where('user_id_1', $userId)->orWhere('user_id_2', $userId);
            })
            ->where('status', 'accepted')
            ->count();

        $recentActivity = ActivityLog::with('user')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->take(10)
            ->get();

        return response()->json([
            'projects_count'  => $projectsCount,
            'recent_projects' => ProjectResource::collection($recentProjects),
            'tasks_by_status' => $tasksByStatus,
            'hours_this_week' => (float) $hoursThisWeek,
            'friends_count'   => $friendsCount,
            'recent_activity' => ActivityLogResource::collection($recentActivity),
        ]);
    }
}
