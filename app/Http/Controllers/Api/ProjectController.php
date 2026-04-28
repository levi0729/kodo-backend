<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\ActivityLog;
use App\Models\Channel;
use App\Models\Participant;
use App\Models\Project;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = Auth::id();

        // Only show projects the user owns or is a participant of
        $participantProjectIds = Participant::where('entity_type', 'project')
            ->where('user_id', $userId)
            ->pluck('entity_id');

        $query = Project::with('owner')
            ->withCount(['tasks', 'teams'])
            ->where(function ($q) use ($userId, $participantProjectIds) {
                $q->where('owner_id', $userId)
                  ->orWhereIn('id', $participantProjectIds);
            });

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
        try {
            $data = $request->validated();
            $data['owner_id'] = Auth::id();
            $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

            $project = DB::transaction(function () use ($data) {
                $project = Project::create($data);

                Participant::create([
                    'entity_type' => 'project',
                    'entity_id'   => $project->id,
                    'user_id'     => Auth::id(),
                    'role'        => 'admin',
                    'joined_at'   => now(),
                ]);

                ActivityLog::create([
                    'user_id'     => Auth::id(),
                    'action'      => 'CREATED_PROJECT',
                    'target_type' => 'project',
                    'target_id'   => $project->id,
                ]);

                // Create default "General" team for this project
                $teamData = [
                    'project_id'  => $project->id,
                    'name'        => 'General',
                    'slug'        => 'general',
                    'description' => null,
                    'color'       => '#6366f1',
                    'visibility'  => 'public',
                    'is_private'  => false,
                    'owner_id'    => Auth::id(),
                ];
                if (Schema::hasColumn('teams', 'is_default')) {
                    $teamData['is_default'] = true;
                }
                $defaultTeam = Team::create($teamData);

                Participant::create([
                    'entity_type' => 'team',
                    'entity_id'   => $defaultTeam->id,
                    'user_id'     => Auth::id(),
                    'role'        => 'admin',
                    'joined_at'   => now(),
                ]);

                Channel::create([
                    'team_id'      => $defaultTeam->id,
                    'name'         => 'general',
                    'slug'         => 'general',
                    'channel_type' => 'standard',
                    'is_default'   => true,
                    'created_by'   => Auth::id(),
                ]);

                return $project;
            });

            $project->load('owner');

            return response()->json([
                'message' => 'Project created.',
                'project' => new ProjectResource($project),
            ], 201);
        } catch (\Throwable $e) {
            \Log::error('Project creation failed', [
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'message' => 'Project creation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function show(Project $project): JsonResponse
    {
        $userId = Auth::id();
        $isMember = $project->owner_id === $userId
            || Participant::where('entity_type', 'project')
                ->where('entity_id', $project->id)
                ->where('user_id', $userId)
                ->exists();

        if (! $isMember) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

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
