<?php

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CalendarEventController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FriendController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ParticipantController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\TimeEntryController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserSettingsController;
use App\Http\Controllers\Api\VerificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Nexus Backend
|--------------------------------------------------------------------------
*/

// ── Health Check ──────────────────────────────────────────
Route::get('/health', fn () => response()->json(['status' => 'ok']));

// ── Public (Guest) ─────────────────────────────────────────
Route::prefix('auth')->middleware('throttle:10,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);
});

// ── Verification (public, used during 2FA flow) ────────────
Route::prefix('verification')->middleware('throttle:10,1')->group(function () {
    Route::post('/send',         [VerificationController::class, 'sendCode']);
    Route::post('/verify',       [VerificationController::class, 'verifyCode']);
    Route::post('/check-device', [VerificationController::class, 'checkDevice']);
});

// ── Protected ──────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/auth/logout',          [AuthController::class, 'logout']);
    Route::get('/auth/me',               [AuthController::class, 'me']);
    Route::post('/auth/change-password', [AuthController::class, 'changePassword']);

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Users
    Route::get('/users',               [UserController::class, 'index']);
    Route::get('/users/{user}',        [UserController::class, 'show']);
    Route::put('/profile',             [UserController::class, 'updateProfile']);
    Route::patch('/profile/status',    [UserController::class, 'updateStatus']);

    // User Settings
    Route::get('/settings',  [UserSettingsController::class, 'show']);
    Route::put('/settings',  [UserSettingsController::class, 'update']);

    // Projects
    Route::apiResource('projects', ProjectController::class);
    Route::post('/projects/{id}/restore', [ProjectController::class, 'restore']);

    // Teams
    Route::apiResource('teams', TeamController::class);
    Route::post('/teams/{team}/join',  [TeamController::class, 'join']);
    Route::post('/teams/{team}/leave', [TeamController::class, 'leave']);

    // Tasks
    Route::apiResource('tasks', TaskController::class);
    Route::post('/tasks/bulk-status', [TaskController::class, 'bulkUpdateStatus']);

    // Chat
    Route::prefix('chat')->group(function () {
        Route::get('/conversations',                [ChatController::class, 'conversations']);
        Route::get('/rooms/{roomId}/messages',      [ChatController::class, 'messages']);
        Route::get('/rooms/{roomId}/poll',          [ChatController::class, 'poll']);
        Route::post('/send',                        [ChatController::class, 'send']);
        Route::patch('/rooms/{roomId}/read',        [ChatController::class, 'markAsRead']);
        Route::patch('/messages/{chatRoom}/pin',    [ChatController::class, 'togglePin']);
        Route::delete('/messages/{chatRoom}',       [ChatController::class, 'deleteMessage']);
    });

    // Friends
    Route::prefix('friends')->group(function () {
        Route::get('/',                  [FriendController::class, 'index']);
        Route::get('/pending',           [FriendController::class, 'pending']);
        Route::get('/sent',              [FriendController::class, 'sent']);
        Route::post('/request',          [FriendController::class, 'sendRequest']);
        Route::patch('/{friend}/accept', [FriendController::class, 'accept']);
        Route::patch('/{friend}/decline',[FriendController::class, 'decline']);
        Route::delete('/{friend}',       [FriendController::class, 'remove']);
    });

    // Time Entries
    Route::apiResource('time-entries', TimeEntryController::class)->except(['show']);
    Route::get('/time-entries/summary', [TimeEntryController::class, 'summary']);

    // Activity Logs
    Route::get('/activity-logs',                     [ActivityLogController::class, 'index']);
    Route::get('/activity-logs/feed',                [ActivityLogController::class, 'feed']);
    Route::get('/activity-logs/project/{projectId}', [ActivityLogController::class, 'forProject']);

    // Participants (project/team membership)
    Route::prefix('participants')->group(function () {
        Route::get('/',        [ParticipantController::class, 'index']);
        Route::post('/',       [ParticipantController::class, 'store']);
        Route::patch('/role',  [ParticipantController::class, 'updateRole']);
        Route::delete('/',     [ParticipantController::class, 'destroy']);
    });

    // Calendar Events
    Route::apiResource('calendar-events', CalendarEventController::class);

    // Notifications
    Route::prefix('notifications')->group(function () {
        Route::get('/',              [NotificationController::class, 'index']);
        Route::patch('/{id}/read',   [NotificationController::class, 'markAsRead']);
        Route::post('/read-all',     [NotificationController::class, 'markAllRead']);
        Route::delete('/{id}',       [NotificationController::class, 'destroy']);
    });
});
