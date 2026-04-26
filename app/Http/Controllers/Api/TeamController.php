<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTeamRequest;
use App\Http\Requests\UpdateTeamRequest;
use App\Http\Resources\TeamResource;
use App\Models\ActivityLog;
use App\Models\Channel;
use App\Models\Participant;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TeamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = Auth::id();

        // Only show teams the user is a member of or owns
        $memberTeamIds = Participant::where('entity_type', 'team')
            ->where('user_id', $userId)
            ->pluck('entity_id');

        $query = Team::with('owner', 'project', 'participants')
            ->withCount('tasks');

        if ($projectId = $request->query('project_id')) {
            $query->where('project_id', $projectId)
                ->where(function ($q) use ($userId, $memberTeamIds) {
                    $q->whereIn('id', $memberTeamIds)
                      ->orWhere('owner_id', $userId)
                      ->orWhere(function ($q2) {
                          $q2->where('visibility', 'public')->where('is_private', false);
                      });
                });
        } else {
            $query->where(function ($q) use ($userId, $memberTeamIds) {
                $q->whereIn('id', $memberTeamIds)
                  ->orWhere('owner_id', $userId);
            });
        }

        $perPage = min((int) $request->query('per_page', 15), 100);
        $teams = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'teams' => TeamResource::collection($teams),
            'meta'  => [
                'current_page' => $teams->currentPage(),
                'last_page'    => $teams->lastPage(),
                'per_page'     => $teams->perPage(),
                'total'        => $teams->total(),
            ],
        ]);
    }

    public function store(StoreTeamRequest $request): JsonResponse
    {
        $data = $request->validated();

        $team = Team::create([
            'project_id'    => $data['project_id'] ?? null,
            'name'          => $data['name'],
            'slug'          => Str::slug($data['name']),
            'description'   => $data['description'] ?? null,
            'color'         => $data['color'] ?? '#7360F9',
            'visibility'    => $data['visibility'] ?? (($data['is_private'] ?? false) ? 'private' : 'public'),
            'owner_id'      => Auth::id(),
            'is_private'    => $data['is_private'] ?? false,
            'password_hash' => isset($data['password']) ? Hash::make($data['password']) : null,
        ]);

        Participant::create([
            'entity_id'   => $team->id,
            'user_id'     => Auth::id(),
            'entity_type' => 'team',
            'joined_at'   => now(),
            'role'        => 'admin',
        ]);

        // Create default #general channel for the team
        Channel::create([
            'team_id'      => $team->id,
            'name'         => 'general',
            'slug'         => 'general',
            'channel_type' => 'standard',
            'is_default'   => true,
            'created_by'   => Auth::id(),
        ]);

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'CREATED_TEAM',
            'target_type' => 'team',
            'target_id'   => $team->id,
        ]);

        $team->load('owner', 'project');

        return response()->json([
            'message' => 'Team created.',
            'team'    => new TeamResource($team),
        ], 201);
    }

    public function show(Team $team): JsonResponse
    {
        $userId = Auth::id();
        $isMember = $team->owner_id === $userId
            || Participant::where('entity_type', 'team')
                ->where('entity_id', $team->id)
                ->where('user_id', $userId)
                ->exists();

        if (! $isMember) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $team->load(['owner', 'project', 'tasks', 'participants.user']);
        $team->loadCount('tasks');

        return response()->json([
            'team' => new TeamResource($team),
        ]);
    }

    public function update(UpdateTeamRequest $request, Team $team): JsonResponse
    {
        if ($team->owner_id !== Auth::id()) {
            abort(403, 'You do not own this team.');
        }

        $data = $request->validated();

        if (isset($data['password'])) {
            $data['password_hash'] = Hash::make($data['password']);
            unset($data['password']);
        }

        $team->update($data);

        return response()->json([
            'message' => 'Team updated.',
            'team'    => new TeamResource($team->fresh(['owner', 'project'])),
        ]);
    }

    public function destroy(Team $team): JsonResponse
    {
        if ($team->owner_id !== Auth::id()) {
            abort(403, 'You do not own this team.');
        }

        $team->delete();

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'DELETED_TEAM',
            'target_type' => 'team',
            'target_id'   => $team->id,
        ]);

        return response()->json([
            'message' => 'Team deleted.',
        ]);
    }

    public function join(Request $request, Team $team): JsonResponse
    {
        $exists = Participant::where('entity_id', $team->id)
            ->where('entity_type', 'team')
            ->where('user_id', Auth::id())
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Already a member.'], 409);
        }

        if ($team->is_private) {
            $request->validate(['password' => 'required|string']);

            if (! Hash::check($request->password, $team->password_hash)) {
                return response()->json(['message' => 'Incorrect team password.'], 403);
            }
        }

        Participant::create([
            'entity_id'   => $team->id,
            'user_id'     => Auth::id(),
            'entity_type' => 'team',
            'joined_at'   => now(),
            'role'        => 'member',
        ]);

        return response()->json([
            'message' => 'Joined team successfully.',
        ]);
    }

    public function leave(Team $team): JsonResponse
    {
        if ($team->owner_id === Auth::id()) {
            return response()->json([
                'message' => 'Team owners cannot leave. Transfer ownership first.',
            ], 403);
        }

        $deleted = Participant::where('entity_id', $team->id)
            ->where('entity_type', 'team')
            ->where('user_id', Auth::id())
            ->delete();

        if (! $deleted) {
            return response()->json(['message' => 'Not a member of this team.'], 404);
        }

        return response()->json([
            'message' => 'Left team successfully.',
        ]);
    }
}
