<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FriendResource;
use App\Http\Resources\UserResource;
use App\Models\Friend;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FriendController extends Controller
{
    /**
     * List all friends of the authenticated user.
     */
    public function index(): JsonResponse
    {
        $userId = Auth::id();

        $friends = Friend::where(function ($q) use ($userId) {
                $q->where('user_id_1', $userId)
                  ->orWhere('user_id_2', $userId);
            })
            ->where('status', 'accepted')
            ->with(['userOne', 'userTwo'])
            ->get();

        // Map to the "other" user
        $friendUsers = $friends->map(function ($friend) use ($userId) {
            return $friend->user_id_1 === $userId
                ? new UserResource($friend->userTwo)
                : new UserResource($friend->userOne);
        });

        return response()->json([
            'friends' => $friendUsers,
        ]);
    }

    /**
     * List pending friend requests (received).
     */
    public function pending(): JsonResponse
    {
        $requests = Friend::where('user_id_2', Auth::id())
            ->where('status', 'pending')
            ->with('userOne')
            ->get();

        return response()->json([
            'pending_requests' => FriendResource::collection($requests),
        ]);
    }

    /**
     * List sent friend requests (by current user, still pending).
     */
    public function sent(): JsonResponse
    {
        $requests = Friend::where('user_id_1', Auth::id())
            ->where('status', 'pending')
            ->with('userTwo')
            ->get();

        return response()->json([
            'sent_requests' => FriendResource::collection($requests),
        ]);
    }

    /**
     * Send a friend request.
     */
    public function sendRequest(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => ['required', 'exists:users,id', 'different:' . Auth::id()],
        ]);

        $userId  = Auth::id();
        $friendId = $request->user_id;

        // Check for existing relationship in either direction
        $existing = Friend::where(function ($q) use ($userId, $friendId) {
            $q->where('user_id_1', $userId)->where('user_id_2', $friendId);
        })->orWhere(function ($q) use ($userId, $friendId) {
            $q->where('user_id_1', $friendId)->where('user_id_2', $userId);
        })->first();

        if ($existing) {
            return response()->json([
                'message' => 'Friend relationship already exists.',
                'status'  => $existing->status,
            ], 409);
        }

        $friend = Friend::create([
            'user_id_1' => $userId,
            'user_id_2' => $friendId,
            'status'    => 'pending',
        ]);

        return response()->json([
            'message' => 'Friend request sent.',
            'friend'  => new FriendResource($friend->load(['userOne', 'userTwo'])),
        ], 201);
    }

    /**
     * Accept a friend request.
     */
    public function accept(Friend $friend): JsonResponse
    {
        if ($friend->user_id_2 !== Auth::id()) {
            abort(403, 'You can only accept requests sent to you.');
        }

        if ($friend->status !== 'pending') {
            return response()->json(['message' => 'Request is not pending.'], 422);
        }

        $friend->update(['status' => 'accepted']);

        return response()->json([
            'message' => 'Friend request accepted.',
        ]);
    }

    /**
     * Decline a friend request.
     */
    public function decline(Friend $friend): JsonResponse
    {
        if ($friend->user_id_2 !== Auth::id()) {
            abort(403, 'You can only decline requests sent to you.');
        }

        $friend->update(['status' => 'declined']);

        return response()->json([
            'message' => 'Friend request declined.',
        ]);
    }

    /**
     * Remove a friend.
     */
    public function remove(Friend $friend): JsonResponse
    {
        $userId = Auth::id();

        if ($friend->user_id_1 !== $userId && $friend->user_id_2 !== $userId) {
            abort(403, 'Not your friendship.');
        }

        $friend->delete();

        return response()->json([
            'message' => 'Friend removed.',
        ]);
    }
}
