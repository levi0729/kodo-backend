<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    /**
     * List all users (searchable).
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        if ($search = $request->query('search')) {
            // Sanitize wildcards to prevent LIKE injection
            $sanitized = str_replace(['%', '_'], ['\%', '\_'], $search);
            $query->where(function ($q) use ($sanitized) {
                $q->where('username', 'ILIKE', "%{$sanitized}%")
                  ->orWhere('email', 'ILIKE', "%{$sanitized}%")
                  ->orWhere('display_name', 'ILIKE', "%{$sanitized}%");
            });
        }

        $perPage = min((int) $request->query('per_page', 20), 100);
        $users = $query->orderBy('username')->paginate($perPage);

        return response()->json([
            'users' => UserResource::collection($users),
            'meta'  => [
                'current_page' => $users->currentPage(),
                'last_page'    => $users->lastPage(),
                'per_page'     => $users->perPage(),
                'total'        => $users->total(),
            ],
        ]);
    }

    /**
     * Show a single user's profile.
     */
    public function show(User $user): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Update the authenticated user's profile.
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $user->update($request->validated());

        return response()->json([
            'message' => 'Profile updated.',
            'user'    => new UserResource($user->fresh()),
        ]);
    }

    /**
     * Update user's online status.
     */
    public function updateStatus(Request $request): JsonResponse
    {
        $request->validate([
            'presence_status' => ['required', 'string', 'in:online,offline,away,busy,dnd,brb,invisible'],
        ]);

        /** @var User $user */
        $user = Auth::user();
        $user->update([
            'presence_status' => $request->presence_status,
            'last_seen_at'    => now(),
        ]);

        return response()->json([
            'message' => 'Status updated.',
            'user'    => new UserResource($user->fresh()),
        ]);
    }
}
