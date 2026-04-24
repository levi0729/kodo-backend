<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\Message;
use App\Models\Participant;
use App\Services\MentionParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ChannelController extends Controller
{
    /**
     * Check if the current user is a member or owner of the given team.
     */
    private function isTeamMemberOrOwner(int $teamId): bool
    {
        $userId = Auth::id();

        $isMember = Participant::where('entity_type', 'team')
            ->where('entity_id', $teamId)
            ->where('user_id', $userId)
            ->exists();

        if ($isMember) return true;

        return \App\Models\Team::where('id', $teamId)->where('owner_id', $userId)->exists();
    }

    public function index(Request $request): JsonResponse
    {
        $teamId = $request->query('team_id');

        if (! $teamId) {
            return response()->json(['message' => 'team_id is required.'], 422);
        }

        if (! $this->isTeamMemberOrOwner((int) $teamId)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $channels = Channel::where('team_id', $teamId)
            ->withCount('messages')
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        // Auto-create a default #general channel if team has none
        if ($channels->isEmpty()) {
            $team = \App\Models\Team::find($teamId);
            if ($team) {
                $general = Channel::create([
                    'team_id'      => $teamId,
                    'name'         => 'general',
                    'slug'         => 'general',
                    'channel_type' => 'standard',
                    'is_default'   => true,
                    'created_by'   => $team->owner_id,
                ]);
                $general->loadCount('messages');
                $channels = collect([$general]);
            }
        }

        return response()->json(['channels' => $channels]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'team_id'      => 'required|integer|exists:teams,id',
            'name'         => 'required|string|max:255',
            'description'  => 'nullable|string',
            'channel_type' => 'nullable|string|in:general,standard,public,private,announcement',
        ]);

        // Verify user is admin/owner of the team
        $isOwner = \App\Models\Team::where('id', $data['team_id'])->where('owner_id', Auth::id())->exists();
        if (! $isOwner) {
            $participant = Participant::where('entity_type', 'team')
                ->where('entity_id', $data['team_id'])
                ->where('user_id', Auth::id())
                ->first();
            if (! $participant || ! in_array($participant->role, ['admin', 'owner'])) {
                return response()->json(['message' => 'Only team admins can create channels.'], 403);
            }
        }

        $channel = Channel::create([
            'team_id'      => $data['team_id'],
            'name'         => $data['name'],
            'slug'         => Str::slug($data['name']),
            'description'  => $data['description'] ?? null,
            'channel_type' => $data['channel_type'] ?? 'standard',
            'created_by'   => Auth::id(),
        ]);

        return response()->json(['channel' => $channel], 201);
    }

    public function show(Channel $channel): JsonResponse
    {
        if (! $this->isTeamMemberOrOwner($channel->team_id)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $channel->load('creator', 'team');
        $channel->loadCount('messages');

        return response()->json(['channel' => $channel]);
    }

    public function update(Request $request, Channel $channel): JsonResponse
    {
        $isOwner = \App\Models\Team::where('id', $channel->team_id)->where('owner_id', Auth::id())->exists();
        if (! $isOwner) {
            $participant = Participant::where('entity_type', 'team')
                ->where('entity_id', $channel->team_id)
                ->where('user_id', Auth::id())
                ->first();
            if (! $participant || ! in_array($participant->role, ['admin', 'owner'])) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }
        }

        $data = $request->validate([
            'name'         => 'sometimes|string|max:255',
            'description'  => 'nullable|string',
            'channel_type' => 'sometimes|string|in:general,standard,public,private,announcement',
        ]);

        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $channel->update($data);

        return response()->json(['channel' => $channel->fresh()]);
    }

    public function destroy(Channel $channel): JsonResponse
    {
        $isOwner = \App\Models\Team::where('id', $channel->team_id)->where('owner_id', Auth::id())->exists();
        if (! $isOwner) {
            $participant = Participant::where('entity_type', 'team')
                ->where('entity_id', $channel->team_id)
                ->where('user_id', Auth::id())
                ->first();
            if (! $participant || ! in_array($participant->role, ['admin', 'owner'])) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }
        }

        if ($channel->is_default) {
            return response()->json(['message' => 'Cannot delete the default channel.'], 403);
        }

        $channel->delete();

        return response()->json(['message' => 'Channel deleted.']);
    }

    /**
     * Get messages for a channel.
     */
    public function messages(Request $request, Channel $channel): JsonResponse
    {
        if (! $this->isTeamMemberOrOwner($channel->team_id)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $perPage = min((int) $request->query('per_page', 50), 100);

        $messages = Message::where('channel_id', $channel->id)
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
     * Send a message to a channel.
     */
    public function sendMessage(Request $request, Channel $channel): JsonResponse
    {
        if (! $this->isTeamMemberOrOwner($channel->team_id)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $data = $request->validate([
            'content'           => 'required|string|max:5000',
            'content_type'      => 'nullable|string|in:text,rich_text,code,system,mixed',
            'parent_message_id' => 'nullable|integer|exists:messages,id',
        ]);

        $message = Message::create([
            'channel_id'        => $channel->id,
            'sender_id'         => Auth::id(),
            'content'           => $data['content'],
            'content_type'      => $data['content_type'] ?? 'text',
            'parent_message_id' => $data['parent_message_id'] ?? null,
        ]);

        // Update thread count on parent
        if ($message->parent_message_id) {
            $parent = Message::find($message->parent_message_id);
            $parent?->update([
                'thread_reply_count'   => $parent->thread_reply_count + 1,
                'thread_last_reply_at' => now(),
            ]);
        }

        MentionParser::handleMessage($message);

        $message->load('sender', 'reactions', 'mentions.mentioned', 'attachments');

        return response()->json(['message' => $message], 201);
    }
}
