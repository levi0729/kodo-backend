<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'project_id'      => $this->project_id,
            'team_id'         => $this->team_id,
            'bucket_id'       => $this->bucket_id,
            'title'           => $this->title,
            'description'     => $this->description,
            'status'          => $this->status,
            'priority'        => $this->priority,
            'start_date'      => $this->start_date?->toDateString(),
            'due_date'        => $this->due_date?->toDateString(),
            'completed_at'    => $this->completed_at?->toIso8601String(),
            'estimated_hours' => $this->estimated_hours ? (float) $this->estimated_hours : null,
            'actual_hours'    => $this->actual_hours ? (float) $this->actual_hours : null,
            'progress'        => $this->progress,
            'labels'          => $this->labels,
            'created_by'      => $this->created_by,
            'creator'         => new UserResource($this->whenLoaded('creator')),
            'project'         => new ProjectResource($this->whenLoaded('project')),
            'team'            => new TeamResource($this->whenLoaded('team')),
            'assignees'       => UserResource::collection($this->whenLoaded('assignees')),
            'created_at'      => $this->created_at?->toIso8601String(),
            'updated_at'      => $this->updated_at?->toIso8601String(),
        ];
    }
}
