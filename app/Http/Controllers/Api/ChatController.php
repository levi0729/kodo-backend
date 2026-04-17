<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendMessageRequest;
use App\Http\Resources\ChatMessageResource;
use App\Models\ChatRoom;
use App\Models\ChatRoomReaction;
use App\Models\Participant;
use App\Models\Team;
use App\Services\MentionParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    /**
     * Multiplier used to build a deterministic DM room ID from two user IDs.
     * room_id = min(a,b) * DM_ROOM_MULTIPLIER + max(a,b)
     * Supports user IDs up to ~2 billion (BIGINT safe).
     */
    private const DM_ROOM_MULTIPLIER = 1_000_000_000;
    public function conversations(): JsonResponse
    {
        $userId = Auth::id();

        // Team IDs the user belongs to (participants + owned teams)
        $teamIds = Participant::where('entity_type', 'team')
            ->where('user_id', $userId)
            ->pluck('entity_id')
            ->merge(Team::where('owner_id', $userId)->pluck('id'))
            ->unique();

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
            ->with(['sender', 'receiver', 'reactions', 'attachments'])
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
        $this->authorizeRoomAccess($roomId);

        $perPage = min((int) $request->query('per_page', 50), 100);

        $messages = ChatRoom::where('room_id', $roomId)
            ->where('is_deleted', false)
            ->with(['sender', 'receiver', 'reactions', 'attachments'])
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

        $this->authorizeRoomAccess($roomId);

        $query = ChatRoom::where('room_id', $roomId)
            ->where('id', '>', $sinceId)
            ->where('is_deleted', false);

        // For DM rooms, filter to only messages involving this user
        $isDm = ChatRoom::where('room_id', $roomId)->where('room_type', 'dm')->exists();
        if ($isDm) {
            $query->where(function ($q) use ($userId) {
                $q->where('sender_id', $userId)
                  ->orWhere('receiver_id', $userId);
            });
        }

        $messages = $query->with(['sender', 'receiver', 'reactions', 'attachments'])
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
            if (! $this->isTeamMember((int) $request->team_id, $senderId)) {
                abort(403, 'You are not a member of this team.');
            }

            $roomId     = (int) $request->team_id;
            $receiverId = $senderId;
            $roomType   = 'team';
        } else {
            // DM — deterministic room_id
            $receiverId = (int) $request->receiver_id;
            $roomId     = min($senderId, $receiverId) * self::DM_ROOM_MULTIPLIER + max($senderId, $receiverId);
            $roomType   = 'dm';
        }

        $message = ChatRoom::create([
            'room_id'     => $roomId,
            'room_type'   => $roomType,
            'sender_id'   => $senderId,
            'receiver_id' => $receiverId,
            'message'     => $request->message ?? '',
            'sent_at'     => now(),
        ]);

        // Persist uploaded attachments linked to this chat_rooms row
        $attachments = $request->input('attachments', []);
        if (is_array($attachments)) {
            foreach ($attachments as $att) {
                \App\Models\ChatRoomAttachment::create([
                    'chat_room_id' => $message->id,
                    'uploaded_by'  => $senderId,
                    'file_name'    => $att['file_name'],
                    'file_type'    => $att['file_type'] ?? null,
                    'file_size'    => $att['file_size'] ?? null,
                    'file_url'     => $att['file_url'],
                    'width'        => $att['width'] ?? null,
                    'height'       => $att['height'] ?? null,
                ]);
            }
        }

        MentionParser::handleChatRoom(
            $message->message ?? '',
            $senderId,
            [
                'team_id'    => $request->team_id ? (int) $request->team_id : null,
                'action_url' => "/messages?room={$roomId}",
            ]
        );

        $message->load('sender', 'receiver', 'reactions', 'attachments');

        return response()->json([
            'message' => new ChatMessageResource($message),
        ], 201);
    }

    public function markAsRead(int $roomId): JsonResponse
    {
        $userId = Auth::id();
        $this->authorizeRoomAccess($roomId);

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

    /**
     * Toggle an emoji reaction on a chat message.
     */
    public function toggleReaction(Request $request, ChatRoom $chatRoom): JsonResponse
    {
        $this->authorizeRoomAccess((int) $chatRoom->room_id);

        $data = $request->validate([
            'emoji' => ['required', 'string', 'max:50'],
        ]);

        $userId = Auth::id();

        $existing = ChatRoomReaction::where('chat_room_id', $chatRoom->id)
            ->where('user_id', $userId)
            ->where('emoji', $data['emoji'])
            ->first();

        if ($existing) {
            $existing->delete();
            $action = 'removed';
        } else {
            ChatRoomReaction::create([
                'chat_room_id' => $chatRoom->id,
                'user_id'      => $userId,
                'emoji'        => $data['emoji'],
            ]);
            $action = 'added';
        }

        $chatRoom->load(['sender', 'receiver', 'reactions', 'attachments']);

        return response()->json([
            'action'  => $action,
            'message' => new ChatMessageResource($chatRoom),
        ]);
    }

    private function isTeamMember(int $teamId, int $userId): bool
    {
        // Check participants table OR team ownership
        return Participant::where('entity_type', 'team')
                ->where('entity_id', $teamId)
                ->where('user_id', $userId)
                ->exists()
            || Team::where('id', $teamId)->where('owner_id', $userId)->exists();
    }

    private function authorizeRoomAccess(int $roomId): void
    {
        $userId = Auth::id();

        // Determine room type from stored data instead of a magic number threshold
        $roomType = ChatRoom::where('room_id', $roomId)->value('room_type');

        if ($roomType === 'team' || ($roomType === null && Team::where('id', $roomId)->exists())) {
            // Team room — check membership
            if (! $this->isTeamMember($roomId, $userId)) {
                abort(403, 'You are not a member of this team.');
            }
        } else {
            // DM room — verify the user is a participant by checking stored messages
            $isParticipant = ChatRoom::where('room_id', $roomId)
                ->where(function ($q) use ($userId) {
                    $q->where('sender_id', $userId)
                      ->orWhere('receiver_id', $userId);
                })
                ->exists();

            // Also accept if user ID is encoded in the room_id (first message case)
            if (! $isParticipant) {
                $otherUserId = $roomId % self::DM_ROOM_MULTIPLIER;
                $minUserId   = intdiv($roomId, self::DM_ROOM_MULTIPLIER);
                if ($userId !== $otherUserId && $userId !== $minUserId) {
                    abort(403, 'You are not part of this conversation.');
                }
            }
        }
    }
}
