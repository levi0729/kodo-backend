<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Register a new user.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'username'        => $request->username,
            'email'           => $request->email,
            'password'        => Hash::make($request->password),
            'display_name'    => $request->input('display_name', $request->username),
            'job_title'       => $request->input('job_title', ''),
            'avatar_url'      => null,
            'presence_status' => 'online',
            'last_seen_at'    => now(),
            'is_active'       => true,
        ]);

        UserSetting::create([
            'user_id'               => $user->id,
            'theme'                 => 'dark',
            'language'              => 'hu',
            'notifications_enabled' => true,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful.',
            'user'    => new UserResource($user),
            'token'   => $token,
        ], 201);
    }

    /**
     * Login an existing user.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        // Check if user exists
        if (!$user) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        // Check if account is locked
        if ($user->locked_until && now()->lt($user->locked_until)) {
            $minutes = now()->diffInMinutes($user->locked_until, false);
            return response()->json([
                'message' => "Account locked. Try again in {$minutes} minutes.",
            ], 423);
        }

        // Check if account is active
        if (!$user->is_active) {
            return response()->json(['message' => 'Account is deactivated.'], 403);
        }

        // Attempt auth
        if (!Hash::check($request->password, $user->password)) {
            $attempts = ($user->failed_login_attempts ?? 0) + 1;
            $update = ['failed_login_attempts' => $attempts];

            if ($attempts >= 5) {
                $update['locked_until'] = now()->addMinutes(15);
                $update['failed_login_attempts'] = 0;
            }

            $user->update($update);

            $remaining = 5 - $attempts;
            if ($remaining > 0) {
                return response()->json(['message' => "Invalid credentials. {$remaining} attempts remaining."], 401);
            }
            return response()->json(['message' => 'Account locked for 15 minutes due to too many failed attempts.'], 423);
        }

        // Reset failed attempts on success
        $user->update([
            'failed_login_attempts' => 0,
            'locked_until'          => null,
            'presence_status'       => 'online',
            'last_seen_at'          => now(),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'user'    => new UserResource($user),
            'token'   => $token,
        ]);
    }

    /**
     * Logout the authenticated user.
     */
    public function logout(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $user->update([
            'presence_status' => 'offline',
            'last_seen_at'    => now(),
        ]);

        $user->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * Change the authenticated user's password.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'password'         => 'required|string|min:8|confirmed',
        ]);

        $user = Auth::user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Current password is incorrect.'], 422);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json(['message' => 'Password changed successfully.']);
    }

    /**
     * Get the currently authenticated user.
     */
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $user->load('settings');

        return response()->json([
            'user'     => new UserResource($user),
            'settings' => $user->settings,
        ]);
    }
}
