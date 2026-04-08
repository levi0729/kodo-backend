<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Participant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ParticipantController extends Controller
{
    /**
     * Check if the authenticated user is an admin or owner of the given entity.
     */
    private function isAdminOrOwner(string $entityType, int $entityId): bool
    {
        return Participant::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('user_id', Auth::id())
            ->whereIn('role', ['admin', 'owner'])
            ->exists();
    }

    /**
     * Check if the authenticated user is a member of the given entity.
     */
    private function isMember(string $entityType, int $entityId): bool
    {
        return Participant::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('user_id', Auth::id())
            ->exists();
    }

    /**
     * List members of a project or team.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'entity_type' => ['required', 'string', 'in:project,team'],
            'entity_id'   => ['required', 'integer'],
        ]);

        if (! $this->isMember($request->entity_type, $request->entity_id)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $participants = Participant::with('user')
            ->where('entity_type', $request->entity_type)
            ->where('entity_id', $request->entity_id)
            ->get();

        return response()->json([
            'participants' => $participants->map(fn ($p) => [
                'user'      => new UserResource($p->user),
                'role'      => $p->role,
                'joined_at' => $p->joined_at?->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Add a participant to a project or team.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'entity_type' => ['required', 'string', 'in:project,team'],
            'entity_id'   => ['required', 'integer'],
            'user_id'     => ['required', 'exists:users,id'],
            'role'        => ['sometimes', 'string', 'max:255'],
        ]);

        if (! $this->isAdminOrOwner($request->entity_type, $request->entity_id)) {
            return response()->json(['message' => 'Forbidden. Only admins or owners can add participants.'], 403);
        }

        $exists = Participant::where('entity_type', $request->entity_type)
            ->where('entity_id', $request->entity_id)
            ->where('user_id', $request->user_id)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'User is already a participant.'], 409);
        }

        $participant = Participant::create([
            'entity_type' => $request->entity_type,
            'entity_id'   => $request->entity_id,
            'user_id'     => $request->user_id,
            'role'        => $request->input('role', 'member'),
            'joined_at'   => now(),
        ]);

        $participant->load('user');

        return response()->json([
            'message'     => 'Participant added.',
            'participant' => [
                'user'      => new UserResource($participant->user),
                'role'      => $participant->role,
                'joined_at' => $participant->joined_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Update a participant's role.
     */
    public function updateRole(Request $request): JsonResponse
    {
        $request->validate([
            'entity_type' => ['required', 'string', 'in:project,team'],
            'entity_id'   => ['required', 'integer'],
            'user_id'     => ['required', 'exists:users,id'],
            'role'        => ['required', 'string', 'max:255'],
        ]);

        if (! $this->isAdminOrOwner($request->entity_type, $request->entity_id)) {
            return response()->json(['message' => 'Forbidden. Only admins or owners can change roles.'], 403);
        }

        $participant = Participant::where('entity_type', $request->entity_type)
            ->where('entity_id', $request->entity_id)
            ->where('user_id', $request->user_id)
            ->firstOrFail();

        $participant->update(['role' => $request->role]);

        return response()->json([
            'message' => 'Role updated.',
        ]);
    }

    /**
     * Remove a participant from a project or team.
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'entity_type' => ['required', 'string', 'in:project,team'],
            'entity_id'   => ['required', 'integer'],
            'user_id'     => ['required', 'exists:users,id'],
        ]);

        if (! $this->isAdminOrOwner($request->entity_type, $request->entity_id)) {
            return response()->json(['message' => 'Forbidden. Only admins or owners can remove participants.'], 403);
        }

        $deleted = Participant::where('entity_type', $request->entity_type)
            ->where('entity_id', $request->entity_id)
            ->where('user_id', $request->user_id)
            ->delete();

        if (! $deleted) {
            return response()->json(['message' => 'Participant not found.'], 404);
        }

        return response()->json([
            'message' => 'Participant removed.',
        ]);
    }
}
