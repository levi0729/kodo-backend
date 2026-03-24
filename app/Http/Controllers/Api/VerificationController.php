<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class VerificationController extends Controller
{
    /**
     * Send a verification code to user's email or phone.
     * Called after successful password validation but before granting full access.
     */
    public function sendCode(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'method'  => ['required', 'string', 'in:email,sms'],
        ]);

        $user = User::findOrFail($request->user_id);

        // Invalidate previous unused codes
        VerificationCode::where('user_id', $user->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->update(['expires_at' => now()]);

        // Generate 6-digit code
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        VerificationCode::create([
            'user_id'    => $user->id,
            'code'       => $code,
            'method'     => $request->method,
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
        ]);

        // In a production environment, this would send the code via email or SMS.
        // For development/demo, we return the code in the response.
        $destination = $request->method === 'email'
            ? $this->maskEmail($user->email)
            : $this->maskPhone($user->phone_number);

        return response()->json([
            'message'     => 'Verification code sent.',
            'method'      => $request->method,
            'destination'  => $destination,
            // DEV ONLY: include code for testing — remove in production
            'dev_code'    => $code,
        ]);
    }

    /**
     * Verify the code and complete login.
     */
    public function verifyCode(Request $request): JsonResponse
    {
        $request->validate([
            'user_id'         => ['required', 'integer', 'exists:users,id'],
            'code'            => ['required', 'string', 'size:6'],
            'remember_device' => ['sometimes', 'boolean'],
        ]);

        $user = User::findOrFail($request->user_id);

        $verification = VerificationCode::where('user_id', $user->id)
            ->where('code', $request->code)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest('created_at')
            ->first();

        if (!$verification) {
            return response()->json([
                'message' => 'Invalid or expired verification code.',
            ], 422);
        }

        // Mark code as used
        $verification->update(['used_at' => now()]);

        // Update user status
        $user->update([
            'failed_login_attempts' => 0,
            'locked_until'          => null,
            'presence_status'       => 'online',
            'last_seen_at'          => now(),
        ]);

        // Create auth token
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = [
            'message' => 'Verification successful.',
            'user'    => new \App\Http\Resources\UserResource($user),
            'token'   => $token,
        ];

        // Handle "remember device for 30 days"
        if ($request->boolean('remember_device')) {
            $deviceToken = Str::random(64);

            // Clean up expired tokens for this user
            TrustedDevice::where('user_id', $user->id)
                ->where('expires_at', '<', now())
                ->delete();

            TrustedDevice::create([
                'user_id'      => $user->id,
                'device_token' => hash('sha256', $deviceToken),
                'expires_at'   => now()->addDays(30),
                'created_at'   => now(),
            ]);

            $response['device_token'] = $deviceToken;
        }

        return response()->json($response);
    }

    /**
     * Check if a device is trusted (skip verification).
     */
    public function checkDevice(Request $request): JsonResponse
    {
        $request->validate([
            'user_id'      => ['required', 'integer', 'exists:users,id'],
            'device_token' => ['required', 'string'],
        ]);

        $hashedToken = hash('sha256', $request->device_token);

        $trusted = TrustedDevice::where('user_id', $request->user_id)
            ->where('device_token', $hashedToken)
            ->where('expires_at', '>', now())
            ->exists();

        return response()->json([
            'trusted' => $trusted,
        ]);
    }

    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        $name = $parts[0];
        $masked = substr($name, 0, 2) . str_repeat('*', max(strlen($name) - 2, 0));
        return $masked . '@' . $parts[1];
    }

    private function maskPhone(?string $phone): string
    {
        if (!$phone) return '***';
        $len = strlen($phone);
        if ($len <= 4) return str_repeat('*', $len);
        return str_repeat('*', $len - 4) . substr($phone, -4);
    }
}
