<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Participant;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskChecklist;
use App\Models\TaskChecklistItem;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaskChecklistController extends Controller
{
    /**
     * Check if the authenticated user can access a task (member of its project or team, or creator/assignee).
     */
    private function canAccessTask(Task $task): bool
    {
        $userId = Auth::id();

        if ($task->created_by === $userId) {
            return true;
        }

        if ($task->assignees()->where('users.id', $userId)->exists()) {
            return true;
        }

        if ($task->project_id) {
            $isMember = Participant::where('entity_type', 'project')
                ->where('entity_id', $task->project_id)
                ->where('user_id', $userId)
                ->exists()
                || Project::where('id', $task->project_id)->where('owner_id', $userId)->exists();
            if ($isMember) return true;
        }

        if ($task->team_id) {
            $isMember = Participant::where('entity_type', 'team')
                ->where('entity_id', $task->team_id)
                ->where('user_id', $userId)
                ->exists()
                || Team::where('id', $task->team_id)->where('owner_id', $userId)->exists();
            if ($isMember) return true;
        }

        return false;
    }

    /**
     * Resolve and authorize a task by ID, returning [task, errorResponse].
     */
    private function resolveTask(int $taskId): array
    {
        $task = Task::find($taskId);

        if (! $task) {
            return [null, response()->json(['message' => 'Task not found.'], 404)];
        }

        if (! $this->canAccessTask($task)) {
            return [null, response()->json(['message' => 'Forbidden.'], 403)];
        }

        return [$task, null];
    }

    // ── Checklists ─────────────────────────────────────────

    /**
     * GET /api/tasks/{taskId}/checklists
     */
    public function index(int $taskId): JsonResponse
    {
        [$task, $error] = $this->resolveTask($taskId);
        if ($error) return $error;

        $checklists = $task->checklists()->with('items')->get();

        return response()->json([
            'checklists' => $checklists->map(fn ($cl) => [
                'id'    => $cl->id,
                'title' => $cl->title,
                'items' => $cl->items->map(fn ($item) => [
                    'id'           => $item->id,
                    'title'        => $item->title,
                    'is_completed' => $item->is_completed,
                ]),
            ]),
        ]);
    }

    /**
     * POST /api/tasks/{taskId}/checklists
     */
    public function store(Request $request, int $taskId): JsonResponse
    {
        [$task, $error] = $this->resolveTask($taskId);
        if ($error) return $error;

        $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $maxPosition = $task->checklists()->max('position') ?? -1;

        $checklist = TaskChecklist::create([
            'task_id'  => $task->id,
            'title'    => $request->input('title'),
            'position' => $maxPosition + 1,
        ]);

        return response()->json([
            'checklist' => [
                'id'    => $checklist->id,
                'title' => $checklist->title,
                'items' => [],
            ],
        ], 201);
    }

    /**
     * PUT /api/tasks/{taskId}/checklists/{checklistId}
     */
    public function update(Request $request, int $taskId, int $checklistId): JsonResponse
    {
        [$task, $error] = $this->resolveTask($taskId);
        if ($error) return $error;

        $checklist = TaskChecklist::where('id', $checklistId)
            ->where('task_id', $task->id)
            ->first();

        if (! $checklist) {
            return response()->json(['message' => 'Checklist not found.'], 404);
        }

        $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $checklist->update([
            'title' => $request->input('title'),
        ]);

        $checklist->load('items');

        return response()->json([
            'checklist' => [
                'id'    => $checklist->id,
                'title' => $checklist->title,
                'items' => $checklist->items->map(fn ($item) => [
                    'id'           => $item->id,
                    'title'        => $item->title,
                    'is_completed' => $item->is_completed,
                ]),
            ],
        ]);
    }

    /**
     * DELETE /api/tasks/{taskId}/checklists/{checklistId}
     */
    public function destroy(int $taskId, int $checklistId): JsonResponse
    {
        [$task, $error] = $this->resolveTask($taskId);
        if ($error) return $error;

        $checklist = TaskChecklist::where('id', $checklistId)
            ->where('task_id', $task->id)
            ->first();

        if (! $checklist) {
            return response()->json(['message' => 'Checklist not found.'], 404);
        }

        $checklist->delete();

        return response()->json([
            'message' => 'Checklist deleted.',
        ]);
    }

    // ── Checklist Items ────────────────────────────────────

    /**
     * POST /api/tasks/{taskId}/checklists/{checklistId}/items
     */
    public function addItem(Request $request, int $taskId, int $checklistId): JsonResponse
    {
        [$task, $error] = $this->resolveTask($taskId);
        if ($error) return $error;

        $checklist = TaskChecklist::where('id', $checklistId)
            ->where('task_id', $task->id)
            ->first();

        if (! $checklist) {
            return response()->json(['message' => 'Checklist not found.'], 404);
        }

        $request->validate([
            'title' => ['required', 'string', 'max:500'],
        ]);

        $maxPosition = $checklist->items()->max('position') ?? -1;

        $item = TaskChecklistItem::create([
            'checklist_id' => $checklist->id,
            'title'        => $request->input('title'),
            'is_completed' => false,
            'position'     => $maxPosition + 1,
        ]);

        return response()->json([
            'item' => [
                'id'           => $item->id,
                'title'        => $item->title,
                'is_completed' => $item->is_completed,
            ],
        ], 201);
    }

    /**
     * PUT /api/tasks/{taskId}/checklists/{checklistId}/items/{itemId}
     */
    public function updateItem(Request $request, int $taskId, int $checklistId, int $itemId): JsonResponse
    {
        [$task, $error] = $this->resolveTask($taskId);
        if ($error) return $error;

        $checklist = TaskChecklist::where('id', $checklistId)
            ->where('task_id', $task->id)
            ->first();

        if (! $checklist) {
            return response()->json(['message' => 'Checklist not found.'], 404);
        }

        $item = TaskChecklistItem::where('id', $itemId)
            ->where('checklist_id', $checklist->id)
            ->first();

        if (! $item) {
            return response()->json(['message' => 'Checklist item not found.'], 404);
        }

        $request->validate([
            'title'        => ['sometimes', 'string', 'max:500'],
            'is_completed' => ['sometimes', 'boolean'],
        ]);

        $updateData = [];

        if ($request->has('title')) {
            $updateData['title'] = $request->input('title');
        }

        if ($request->has('is_completed')) {
            $updateData['is_completed'] = $request->boolean('is_completed');
            if ($request->boolean('is_completed')) {
                $updateData['completed_at'] = now();
                $updateData['completed_by'] = Auth::id();
            } else {
                $updateData['completed_at'] = null;
                $updateData['completed_by'] = null;
            }
        }

        if (! empty($updateData)) {
            $item->update($updateData);
        }

        return response()->json([
            'item' => [
                'id'           => $item->id,
                'title'        => $item->title,
                'is_completed' => $item->is_completed,
            ],
        ]);
    }

    /**
     * DELETE /api/tasks/{taskId}/checklists/{checklistId}/items/{itemId}
     */
    public function destroyItem(int $taskId, int $checklistId, int $itemId): JsonResponse
    {
        [$task, $error] = $this->resolveTask($taskId);
        if ($error) return $error;

        $checklist = TaskChecklist::where('id', $checklistId)
            ->where('task_id', $task->id)
            ->first();

        if (! $checklist) {
            return response()->json(['message' => 'Checklist not found.'], 404);
        }

        $item = TaskChecklistItem::where('id', $itemId)
            ->where('checklist_id', $checklist->id)
            ->first();

        if (! $item) {
            return response()->json(['message' => 'Checklist item not found.'], 404);
        }

        $item->delete();

        return response()->json([
            'message' => 'Checklist item deleted.',
        ]);
    }
}
