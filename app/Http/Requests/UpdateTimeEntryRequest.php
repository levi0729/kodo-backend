<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTimeEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'project_id'    => ['sometimes', 'exists:projects,id'],
            'hours'         => ['sometimes', 'numeric', 'min:0.01', 'max:24'],
            'date'          => ['sometimes', 'date'],
            'activity_type' => ['nullable', 'string', 'max:255'],
            'description'   => ['nullable', 'string', 'max:2000'],
            'task_id'       => ['nullable', 'exists:tasks,id'],
        ];
    }
}
