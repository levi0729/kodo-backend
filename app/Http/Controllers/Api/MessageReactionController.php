<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\Participant;
use App\Models\ConversationParticipant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessageReactionController extends Controller
{
    /**
     * Toggle a reaction on a message (add if missing, remove if exists).
     */
    public function toggle(Request $request, Message $message): JsonResponse
    {
        $this->authorizeMessageAccess($message);

        $data = $request->validate([
            'emoji' => 'required|string|max:50',
        ]);

        $existing = MessageReaction::where('message_id', $message->id)
            ->where('user_id', Auth::id())
            ->where('emoji', $data['emoji'])
            ->first();

        if ($existing) {
            $existing->delete();
            return response()->json(['message' => 'Reaction removed.']);
        }

        MessageReaction::create([
            'message_id' => $message->id,
            'user_id'    => Auth::id(),
            'emoji'      => $data['emoji'],
        ]);

        return response()->json(['message' => 'Reaction added.'], 201);
    }

    /**
     * List reactions for a message.
     */
    public function index(Message $message): JsonResponse
    {
        $this->authorizeMessageAccess($message);

        $reactions = MessageReaction::where('message_id', $message->id)
            ->with('user')
            ->get()
            ->groupBy('emoji')
            ->map(fn ($group) => [
                'emoji' => $group->first()->emoji,
                'count' => $group->count(),
                'users' => $group->pluck('user.display_name'),
            ])
            ->values();

        return response()->json(['reactions' => $reactions]);
    }

    private function authorizeMessageAccess(Message $message): void
    {
        $userId = Auth::id();

        if ($message->channel_id) {
            $channel = $message->channel;
            $isMember = Participant::where('entity_type', 'team')
                ->where('entity_id', $channel->team_id)
                ->where('user_id', $userId)
                ->exists();
            if (! $isMember) {
                abort(403, 'Forbidden.');
            }
        } elseif ($message->conversation_id) {
            $isParticipant = ConversationParticipant::where('conversation_id', $message->conversation_id)
                ->where('user_id', $userId)
                ->whereNull('left_at')
                ->exists();
            if (! $isParticipant) {
                abort(403, 'Forbidden.');
            }
        } else {
            abort(403, 'Forbidden.');
        }
    }
}
