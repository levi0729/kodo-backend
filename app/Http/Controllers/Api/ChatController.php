<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendMessageRequest;
use App\Http\Resources\ChatMessageResource;
use App\Models\ChatRoom;
use App\Models\Participant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    public function conversations(): JsonResponse
    {
        $userId = Auth::id();

        // Team IDs the user belongs to (these are also team room_ids)
        $teamIds = Participant::where('entity_type', 'team')
            ->where('user_id', $userId)
            ->pluck('entity_id');

        $conversations = ChatRoom::where(function ($q) use ($userId, $teamIds) {
                // DM conversations
                $q->where(function ($q2) use ($userId) {
                    $q2->where('sender_id', $userId)
                        ->orWhere('receiver_id', $userId);
                })
                // Team conversations (room_id = team_id)
                ->orWhereIn('room_id', $teamIds);
            })
            ->where('is_deleted', false)
            ->with(['sender', 'receiver'])
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('room_id')
            ->map(function ($messages) use ($userId) {
                return [
                    'room_id'        => $messages->first()->room_id,
                    'latest_message' => new ChatMessageResource($messages->first()),
                    'unread_count'   => $messages->where('is_read', false)
                        ->where('receiver_id', $userId)
                        ->count(),
                ];
            })
            ->values();

        return response()->json([
            'conversations' => $conversations,
        ]);
    }

    public function messages(Request $request, int $roomId): JsonResponse
    {
        $userId = Auth::id();

        if ($roomId < 100000) {
            // Team room — verify team membership
            $isMember = Participant::where('entity_type', 'team')
                ->where('entity_id', $roomId)
                ->where('user_id', $userId)
                ->exists();
            if (! $isMember) {
                abort(403, 'You are not a member of this team.');
            }
        } else {
            // DM room — verify the user is one of the two participants
            // room_id = min(a,b) * 100000 + max(a,b)
            $otherUserId = $roomId % 100000;
            $minUserId   = (int) (($roomId - $otherUserId) / 100000);
            if ($userId !== $otherUserId && $userId !== $minUserId) {
                abort(403, 'You are not part of this conversation.');
            }
        }

        $perPage = min((int) $request->query('per_page', 50), 100);

        $messages = ChatRoom::where('room_id', $roomId)
            ->where('is_deleted', false)
            ->with(['sender', 'receiver'])
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'messages' => ChatMessageResource::collection($messages),
            'meta'     => [
                'current_page' => $messages->currentPage(),
                'last_page'    => $messages->lastPage(),
                'total'        => $messages->total(),
            ],
        ]);
    }

    /**
     * Poll for new messages in a room since a given message ID.
     */
    public function poll(Request $request, int $roomId): JsonResponse
    {
        $userId = Auth::id();
        $sinceId = (int) $request->query('since_id', 0);

        // Verify access to the room
        if ($roomId < 100000) {
            $isMember = Participant::where('entity_type', 'team')
                ->where('entity_id', $roomId)
                ->where('user_id', $userId)
                ->exists();
            if (! $isMember) {
                abort(403, 'You are not a member of this team.');
            }
        } else {
            $otherUserId = $roomId % 100000;
            $minUserId   = (int) (($roomId - $otherUserId) / 100000);
            if ($userId !== $otherUserId && $userId !== $minUserId) {
                abort(403, 'You are not part of this conversation.');
            }
        }

        $query = ChatRoom::where('room_id', $roomId)
            ->where('id', '>', $sinceId)
            ->where('is_deleted', false);

        if ($roomId >= 100000) {
            $query->where(function ($q) use ($userId) {
                $q->where('sender_id', $userId)
                  ->orWhere('receiver_id', $userId);
            });
        }

        $messages = $query->with(['sender', 'receiver'])
            ->orderBy('created_at')
            ->limit(50)
            ->get();

        return response()->json([
            'messages' => ChatMessageResource::collection($messages),
        ]);
    }

    public function send(SendMessageRequest $request): JsonResponse
    {
        $senderId = Auth::id();

        if ($request->team_id) {
            // Team message — verify membership
            $isMember = Participant::where('entity_type', 'team')
                ->where('entity_id', (int) $request->team_id)
                ->where('user_id', $senderId)
                ->exists();
            if (! $isMember) {
                abort(403, 'You are not a member of this team.');
            }

            $roomId     = (int) $request->team_id;
            $receiverId = $senderId;
        } else {
            // DM — deterministic room_id
            $receiverId = (int) $request->receiver_id;
            $roomId     = min($senderId, $receiverId) * 100000 + max($senderId, $receiverId);
        }

        $message = ChatRoom::create([
            'room_id'     => $roomId,
            'sender_id'   => $senderId,
            'receiver_id' => $receiverId,
            'message'     => $request->message,
            'sent_at'     => now(),
        ]);

        $message->load('sender', 'receiver');

        return response()->json([
            'message' => new ChatMessageResource($message),
        ], 201);
    }

    public function markAsRead(int $roomId): JsonResponse
    {
        $userId = Auth::id();

        // Verify access to the room
        if ($roomId < 100000) {
            $isMember = Participant::where('entity_type', 'team')
                ->where('entity_id', $roomId)
                ->where('user_id', $userId)
                ->exists();
            if (! $isMember) {
                abort(403, 'You are not a member of this team.');
            }
        } else {
            $otherUserId = $roomId % 100000;
            $minUserId   = (int) (($roomId - $otherUserId) / 100000);
            if ($userId !== $otherUserId && $userId !== $minUserId) {
                abort(403, 'You are not part of this conversation.');
            }
        }

        ChatRoom::where('room_id', $roomId)
            ->where('receiver_id', $userId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json([
            'message' => 'Messages marked as read.',
        ]);
    }

    public function togglePin(ChatRoom $chatRoom): JsonResponse
    {
        // Only participants can pin/unpin
        $userId = Auth::id();
        if ($chatRoom->sender_id !== $userId && $chatRoom->receiver_id !== $userId) {
            abort(403, 'You are not part of this conversation.');
        }

        $chatRoom->update(['is_pinned' => ! $chatRoom->is_pinned]);

        return response()->json([
            'message'   => $chatRoom->is_pinned ? 'Message pinned.' : 'Message unpinned.',
            'is_pinned' => $chatRoom->is_pinned,
        ]);
    }

    public function deleteMessage(ChatRoom $chatRoom): JsonResponse
    {
        if ($chatRoom->sender_id !== Auth::id()) {
            abort(403, 'You can only delete your own messages.');
        }

        $chatRoom->update([
            'is_deleted' => true,
            'deleted_at' => now(),
        ]);

        return response()->json([
            'message' => 'Message deleted.',
        ]);
    }
}
