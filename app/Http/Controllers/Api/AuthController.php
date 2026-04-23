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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

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
            'phone_number'    => $request->phone_number,
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

        $token = $user->createToken('auth_token', ['*'], now()->addDays(30))->plainTextToken;

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
                $user->update($update);
                return response()->json(['message' => 'Account locked for 15 minutes due to too many failed attempts.'], 423);
            }

            $user->update($update);

            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        // Login successful
        $user->update([
            'failed_login_attempts' => 0,
            'locked_until'          => null,
            'presence_status'       => 'online',
            'last_seen_at'          => now(),
        ]);

        $token = $user->createToken('auth_token', ['*'], now()->addDays(30))->plainTextToken;

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
            'password'         => ['required', 'string', 'confirmed', Password::min(8)->mixedCase()->numbers()],
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
     * Send a password reset token to the user's email.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user) {
            // Invalidate any previous reset tokens for this email
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();

            // Generate a plain token and store its hash
            $plainToken = Str::random(6);
            // Use uppercase alphanumeric for easy user entry
            $plainToken = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', Str::random(8)), 0, 6));

            DB::table('password_reset_tokens')->insert([
                'email'      => $user->email,
                'token'      => Hash::make($plainToken),
                'created_at' => now(),
            ]);

            // Send the reset email via Resend HTTP API (same pattern as VerificationController)
            try {
                $userName = $user->display_name ?? $user->username;
                $html = view('emails.password-reset', [
                    'token'    => $plainToken,
                    'userName' => $userName,
                ])->render();

                $response = Http::withToken(config('services.resend.key'))
                    ->post('https://api.resend.com/emails', [
                        'from'    => 'Kodo <onboarding@resend.dev>',
                        'to'      => [$user->email],
                        'subject' => 'Kodo - Jelszó visszaállítás',
                        'html'    => $html,
                    ]);

                if ($response->failed()) {
                    \Log::error('Failed to send password reset email: ' . $response->body());
                }
            } catch (\Throwable $e) {
                \Log::error('Failed to send password reset email: ' . $e->getMessage());
            }
        }

        // Always return success to avoid email enumeration
        $response = [
            'message' => 'If an account exists with that email, we\'ve sent a password reset code.',
        ];

        // DEV ONLY: include token for testing — remove in production
        if (config('app.debug') && isset($plainToken)) {
            $response['dev_token'] = $plainToken;
        }

        return response()->json($response);
    }

    /**
     * Reset the user's password using a valid token.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'string', 'email'],
            'token'    => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$record) {
            return response()->json(['message' => 'Invalid or expired reset token.'], 422);
        }

        // Check if token has expired (60 minutes)
        if (Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json(['message' => 'Invalid or expired reset token.'], 422);
        }

        // Verify the token hash matches
        if (!Hash::check($request->token, $record->token)) {
            return response()->json(['message' => 'Invalid or expired reset token.'], 422);
        }

        // Update the user's password
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'Invalid or expired reset token.'], 422);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // Delete the used token
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json(['message' => 'Password has been reset successfully.']);
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
