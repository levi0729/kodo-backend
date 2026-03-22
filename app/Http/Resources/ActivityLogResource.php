<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'action'      => $this->action,
            'target_type' => $this->target_type,
            'target_id'   => $this->target_id,
            'user'        => new UserResource($this->whenLoaded('user')),
            'created_at'  => $this->created_at?->toIso8601String(),
        ];
    }
}
