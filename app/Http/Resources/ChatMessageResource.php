<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $authId = $request->user()?->id;

        $reactions = null;
        if ($this->relationLoaded('reactions')) {
            $reactions = $this->reactions
                ->groupBy('emoji')
                ->map(fn ($group) => [
                    'emoji'   => $group->first()->emoji,
                    'count'   => $group->count(),
                    'users'   => $group->pluck('user_id')->values(),
                    'reacted' => $authId ? $group->contains('user_id', $authId) : false,
                ])
                ->values();
        }

        $attachments = null;
        if ($this->relationLoaded('attachments')) {
            $attachments = $this->attachments->map(fn ($a) => [
                'id'        => $a->id,
                'file_name' => $a->file_name,
                'file_type' => $a->file_type,
                'file_size' => $a->file_size,
                'file_url'  => $a->file_url,
                'width'     => $a->width,
                'height'    => $a->height,
            ])->values();
        }

        return [
            'id'          => $this->id,
            'room_id'     => $this->room_id,
            'sender_id'   => $this->sender_id,
            'receiver_id' => $this->receiver_id,
            'message'     => $this->message,
            'content'     => $this->message,
            'is_read'     => $this->is_read,
            'is_pinned'   => $this->is_pinned,
            'is_deleted'  => $this->is_deleted,
            'sender'      => new UserResource($this->whenLoaded('sender')),
            'receiver'    => new UserResource($this->whenLoaded('receiver')),
            'reactions'   => $reactions,
            'attachments' => $attachments,
            'sent_at'     => $this->sent_at?->toIso8601String(),
            'read_at'     => $this->read_at?->toIso8601String(),
            'created_at'  => $this->created_at?->toIso8601String(),
        ];
    }
}
