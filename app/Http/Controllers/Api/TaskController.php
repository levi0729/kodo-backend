<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\ActivityLog;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Task::with(['project', 'team', 'creator']);

        if ($projectId = $request->query('project_id')) {
            $query->where('project_id', $projectId);
        }

        if ($teamId = $request->query('team_id')) {
            $query->where('team_id', $teamId);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($priority = $request->query('priority')) {
            $query->where('priority', $priority);
        }

        $perPage = min((int) $request->query('per_page', 20), 100);
        $tasks = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'tasks' => TaskResource::collection($tasks),
            'meta'  => [
                'current_page' => $tasks->currentPage(),
                'last_page'    => $tasks->lastPage(),
                'per_page'     => $tasks->perPage(),
                'total'        => $tasks->total(),
            ],
        ]);
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['created_by'] = Auth::id();

        $assignees = $data['assignees'] ?? null;
        unset($data['assignees']);

        $task = Task::create($data);

        if (! empty($assignees)) {
            $pivotData = [];
            foreach ($assignees as $userId) {
                $pivotData[$userId] = [
                    'assigned_at' => now(),
                    'assigned_by' => Auth::id(),
                ];
            }
            $task->assignees()->attach($pivotData);
        }

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'CREATED_TASK',
            'target_type' => 'task',
            'target_id'   => $task->id,
        ]);

        $task->load(['project', 'team', 'creator', 'assignees']);

        return response()->json([
            'message' => 'Task created.',
            'task'    => new TaskResource($task),
        ], 201);
    }

    public function show(Task $task): JsonResponse
    {
        $task->load(['project', 'team', 'creator', 'assignees']);

        return response()->json([
            'task' => new TaskResource($task),
        ]);
    }

    public function update(UpdateTaskRequest $request, Task $task): JsonResponse
    {
        $data = $request->validated();

        // Auto-set completed_at when status changes to done
        if (isset($data['status']) && $data['status'] === 'done' && $task->status !== 'done') {
            $data['completed_at'] = now();
            $data['progress'] = 100;
        }

        $assignees = null;
        if (array_key_exists('assignees', $data)) {
            $assignees = $data['assignees'];
            unset($data['assignees']);
        }

        $task->update($data);

        if ($assignees !== null) {
            $pivotData = [];
            foreach ($assignees as $userId) {
                $pivotData[$userId] = [
                    'assigned_at' => now(),
                    'assigned_by' => Auth::id(),
                ];
            }
            $task->assignees()->sync($pivotData);
        }

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'UPDATED_TASK',
            'target_type' => 'task',
            'target_id'   => $task->id,
        ]);

        return response()->json([
            'message' => 'Task updated.',
            'task'    => new TaskResource($task->fresh(['project', 'team', 'creator', 'assignees'])),
        ]);
    }

    public function destroy(Task $task): JsonResponse
    {
        $task->delete();

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'DELETED_TASK',
            'target_type' => 'task',
            'target_id'   => $task->id,
        ]);

        return response()->json([
            'message' => 'Task deleted.',
        ]);
    }

    public function bulkUpdateStatus(Request $request): JsonResponse
    {
        $request->validate([
            'tasks'          => ['required', 'array', 'min:1', 'max:50'],
            'tasks.*.id'     => ['required', 'integer', 'exists:tasks,id'],
            'tasks.*.status' => ['required', 'string', 'in:todo,in_progress,in_review,done,blocked,cancelled'],
        ]);

        foreach ($request->tasks as $taskData) {
            $updateData = ['status' => $taskData['status']];
            if ($taskData['status'] === 'done') {
                $updateData['completed_at'] = now();
                $updateData['progress'] = 100;
            }
            Task::where('id', $taskData['id'])->update($updateData);
        }

        return response()->json([
            'message' => 'Tasks updated.',
        ]);
    }
}
