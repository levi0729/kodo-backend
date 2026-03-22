<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\ActivityLog;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Project::with('owner')
            ->withCount(['tasks', 'teams']);

        if ($request->boolean('mine_only')) {
            $query->where('owner_id', Auth::id());
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $perPage = min((int) $request->query('per_page', 15), 100);
        $projects = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'projects' => ProjectResource::collection($projects),
            'meta'     => [
                'current_page' => $projects->currentPage(),
                'last_page'    => $projects->lastPage(),
                'per_page'     => $projects->perPage(),
                'total'        => $projects->total(),
            ],
        ]);
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['owner_id'] = Auth::id();
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        $project = Project::create($data);

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'CREATED_PROJECT',
            'target_type' => 'project',
            'target_id'   => $project->id,
        ]);

        $project->load('owner');

        return response()->json([
            'message' => 'Project created.',
            'project' => new ProjectResource($project),
        ], 201);
    }

    public function show(Project $project): JsonResponse
    {
        $project->load(['owner', 'teams.owner', 'tasks']);
        $project->loadCount(['tasks', 'teams']);

        return response()->json([
            'project' => new ProjectResource($project),
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        $this->authorizeOwnership($project);

        $project->update($request->validated());

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'UPDATED_PROJECT',
            'target_type' => 'project',
            'target_id'   => $project->id,
        ]);

        return response()->json([
            'message' => 'Project updated.',
            'project' => new ProjectResource($project->fresh('owner')),
        ]);
    }

    public function destroy(Project $project): JsonResponse
    {
        $this->authorizeOwnership($project);

        $project->delete();

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'DELETED_PROJECT',
            'target_type' => 'project',
            'target_id'   => $project->id,
        ]);

        return response()->json([
            'message' => 'Project deleted.',
        ]);
    }

    public function restore(int $id): JsonResponse
    {
        $project = Project::withTrashed()->findOrFail($id);
        $this->authorizeOwnership($project);

        $project->restore();

        return response()->json([
            'message' => 'Project restored.',
            'project' => new ProjectResource($project),
        ]);
    }

    private function authorizeOwnership(Project $project): void
    {
        if ($project->owner_id !== Auth::id()) {
            abort(403, 'You do not own this project.');
        }
    }
}
