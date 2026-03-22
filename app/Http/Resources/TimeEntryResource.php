<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimeEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'project_id'    => $this->project_id,
            'task_id'       => $this->task_id,
            'hours'         => (float) $this->hours,
            'date'          => $this->date?->toDateString(),
            'activity_type' => $this->activity_type,
            'description'   => $this->description,
            'user'          => new UserResource($this->whenLoaded('user')),
            'project'       => new ProjectResource($this->whenLoaded('project')),
            'created_at'    => $this->created_at?->toIso8601String(),
        ];
    }
}
