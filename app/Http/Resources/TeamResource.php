<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'project_id'  => $this->project_id,
            'owner_id'    => $this->owner_id,
            'name'        => $this->name,
            'slug'        => $this->slug,
            'description' => $this->description,
            'color'       => $this->color,
            'visibility'  => $this->visibility,
            'is_private'  => $this->is_private,
            'is_archived' => $this->is_archived,
            'is_default'  => $this->is_default,
            'owner'       => new UserResource($this->whenLoaded('owner')),
            'project'     => new ProjectResource($this->whenLoaded('project')),
            'members'     => $this->whenLoaded('participants', fn () =>
                $this->participants->pluck('user_id')->values()
            ),
            'tasks_count' => $this->whenCounted('tasks'),
            'created_at'  => $this->created_at?->toIso8601String(),
            'updated_at'  => $this->updated_at?->toIso8601String(),
        ];
    }
}
