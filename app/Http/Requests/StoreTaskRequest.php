<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'project_id'      => ['required', 'integer', 'exists:projects,id'],
            'team_id'         => ['nullable', 'integer', 'exists:teams,id'],
            'title'           => ['required', 'string', 'max:500'],
            'description'     => ['nullable', 'string', 'max:5000'],
            'status'          => ['sometimes', 'string', 'in:todo,in_progress,in_review,done,blocked,cancelled'],
            'priority'        => ['sometimes', 'string', 'in:urgent,high,medium,low,none'],
            'start_date'      => ['nullable', 'date'],
            'due_date'        => ['nullable', 'date'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'labels'          => ['nullable', 'array'],
            'labels.*'        => ['string', 'max:50'],
            'assignees'       => ['nullable', 'array'],
            'assignees.*'     => ['integer', 'exists:users,id'],
        ];
    }
}
