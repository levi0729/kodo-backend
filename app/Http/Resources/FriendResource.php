<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FriendResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'status'     => $this->status,
            'user_one'   => new UserResource($this->whenLoaded('userOne')),
            'user_two'   => new UserResource($this->whenLoaded('userTwo')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
