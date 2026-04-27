<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'owner_id'        => $this->owner_id,
            'name'            => $this->name,
            'slug'            => $this->slug,
            'description'     => $this->description,
            'color'           => $this->color,
            'icon'            => $this->icon,
            'project_type'    => $this->project_type,
            'status'          => $this->status,
            'start_date'      => $this->start_date?->toDateString(),
            'target_end_date' => $this->target_end_date?->toDateString(),
            'progress'        => $this->progress,
            'owner'           => new UserResource($this->whenLoaded('owner')),
            'teams'           => TeamResource::collection($this->whenLoaded('teams')),
            'tasks_count'     => $this->whenCounted('tasks'),
            'teams_count'     => $this->whenCounted('teams'),
            'created_at'      => $this->created_at?->toIso8601String(),
            'updated_at'      => $this->updated_at?->toIso8601String(),
        ];
    }
}
