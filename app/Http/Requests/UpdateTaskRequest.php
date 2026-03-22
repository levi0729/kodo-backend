<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'           => ['sometimes', 'string', 'max:500'],
            'description'     => ['nullable', 'string', 'max:5000'],
            'status'          => ['sometimes', 'string', 'in:todo,in_progress,in_review,done,blocked,cancelled'],
            'priority'        => ['sometimes', 'string', 'in:urgent,high,medium,low,none'],
            'start_date'      => ['nullable', 'date'],
            'due_date'        => ['nullable', 'date'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'actual_hours'    => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'progress'        => ['sometimes', 'integer', 'min:0', 'max:100'],
            'labels'          => ['nullable', 'array'],
            'labels.*'        => ['string', 'max:50'],
            'team_id'         => ['nullable', 'integer', 'exists:teams,id'],
            'assignees'       => ['nullable', 'array'],
            'assignees.*'     => ['integer', 'exists:users,id'],
        ];
    }
}
