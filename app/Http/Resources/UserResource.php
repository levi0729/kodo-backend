<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'username'        => $this->username,
            'email'           => $this->email,
            'display_name'    => $this->display_name,
            'job_title'       => $this->job_title,
            'department'      => $this->department,
            'avatar_url'      => $this->avatar_url,
            'presence_status' => $this->presence_status,
            'presence_message'=> $this->presence_message,
            'is_active'       => $this->is_active,
            'last_seen_at'    => $this->last_seen_at?->toIso8601String(),
            'created_at'      => $this->created_at?->toIso8601String(),
        ];
    }
}
