<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Services\MentionParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ConversationController extends Controller
{
    /**
     * List conversations the user is part of.
     */
    public function index(): JsonResponse
    {
        $userId = Auth::id();

        $conversationIds = ConversationParticipant::where('user_id', $userId)
            ->whereNull('left_at')
            ->pluck('conversation_id');

        $conversations = Conversation::whereIn('id', $conversationIds)
            ->with(['participants.user', 'creator'])
            ->withCount('messages')
            ->orderByDesc('updated_at')
            ->get();

        return response()->json(['conversations' => $conversations]);
    }

    /**
     * Create a group conversation.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'         => 'nullable|string|max:255',
            'user_ids'     => 'required|array|min:1',
            'user_ids.*'   => 'integer|exists:users,id',
        ]);

        $type = count($data['user_ids']) === 1 ? 'direct' : 'group';

        $conversation = Conversation::create([
            'conversation_type' => $type,
            'name'              => $data['name'] ?? null,
            'created_by'        => Auth::id(),
        ]);

        // Add the creator
        ConversationParticipant::create([
            'conversation_id' => $conversation->id,
            'user_id'         => Auth::id(),
            'role'            => 'admin',
            'joined_at'       => now(),
        ]);

        // Add other participants
        foreach ($data['user_ids'] as $userId) {
            if ($userId !== Auth::id()) {
                ConversationParticipant::create([
                    'conversation_id' => $conversation->id,
                    'user_id'         => $userId,
                    'role'            => 'member',
                    'joined_at'       => now(),
                ]);
            }
        }

        $conversation->load('participants.user');

        return response()->json(['conversation' => $conversation], 201);
    }

    /**
     * Show a conversation with messages.
     */
    public function show(Conversation $conversation): JsonResponse
    {
        $this->authorizeParticipant($conversation);

        $conversation->load('participants.user', 'creator');

        return response()->json(['conversation' => $conversation]);
    }

    /**
     * Get messages in a conversation.
     */
    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeParticipant($conversation);

        $perPage = min((int) $request->query('per_page', 50), 100);

        $messages = Message::where('conversation_id', $conversation->id)
            ->with(['sender', 'reactions.user', 'mentions', 'attachments'])
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'messages' => $messages->items(),
            'meta'     => [
                'current_page' => $messages->currentPage(),
                'last_page'    => $messages->lastPage(),
                'total'        => $messages->total(),
            ],
        ]);
    }

    /**
     * Send a message in a conversation.
     */
    public function sendMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeParticipant($conversation);

        $data = $request->validate([
            'content' => 'required|string|max:5000',
        ]);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id'       => Auth::id(),
            'content'         => $data['content'],
            'content_type'    => 'text',
        ]);

        $conversation->touch();

        MentionParser::handleMessage($message);

        $message->load('sender', 'mentions.mentioned');

        return response()->json(['message' => $message], 201);
    }

    /**
     * Add participants to a group conversation.
     */
    public function addParticipants(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeParticipant($conversation);

        if ($conversation->conversation_type !== 'group') {
            return response()->json(['message' => 'Cannot add participants to a direct conversation.'], 422);
        }

        $data = $request->validate([
            'user_ids'   => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        foreach ($data['user_ids'] as $userId) {
            ConversationParticipant::firstOrCreate([
                'conversation_id' => $conversation->id,
                'user_id'         => $userId,
            ], [
                'role'      => 'member',
                'joined_at' => now(),
            ]);
        }

        $conversation->load('participants.user');

        return response()->json(['conversation' => $conversation]);
    }

    /**
     * Leave a conversation.
     */
    public function leave(Conversation $conversation): JsonResponse
    {
        $participant = ConversationParticipant::where('conversation_id', $conversation->id)
            ->where('user_id', Auth::id())
            ->whereNull('left_at')
            ->first();

        if (! $participant) {
            return response()->json(['message' => 'Not a participant.'], 404);
        }

        $participant->update(['left_at' => now()]);

        return response()->json(['message' => 'Left conversation.']);
    }

    private function authorizeParticipant(Conversation $conversation): void
    {
        $isParticipant = ConversationParticipant::where('conversation_id', $conversation->id)
            ->where('user_id', Auth::id())
            ->whereNull('left_at')
            ->exists();

        if (! $isParticipant) {
            abort(403, 'You are not a participant of this conversation.');
        }
    }
}
