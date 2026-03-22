<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
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
            'sent_at'     => $this->sent_at?->toIso8601String(),
            'read_at'     => $this->read_at?->toIso8601String(),
            'created_at'  => $this->created_at?->toIso8601String(),
        ];
    }
}
