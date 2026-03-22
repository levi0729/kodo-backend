<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserSettingsRequest;
use App\Models\UserSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class UserSettingsController extends Controller
{
    /**
     * Get the authenticated user's settings.
     */
    public function show(): JsonResponse
    {
        $settings = UserSetting::where('user_id', Auth::id())->first();

        if (! $settings) {
            // Create defaults if none exist
            $settings = UserSetting::create([
                'user_id'               => Auth::id(),
                'theme'                 => 'light',
                'language'              => 'en',
                'notifications_enabled' => true,
            ]);
        }

        return response()->json([
            'settings' => $settings,
        ]);
    }

    /**
     * Update settings.
     */
    public function update(UpdateUserSettingsRequest $request): JsonResponse
    {
        $settings = UserSetting::updateOrCreate(
            ['user_id' => Auth::id()],
            $request->validated()
        );

        return response()->json([
            'message'  => 'Settings updated.',
            'settings' => $settings,
        ]);
    }
}
