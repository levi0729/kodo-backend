<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTimeEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'project_id'    => ['required', 'exists:projects,id'],
            'hours'         => ['required', 'numeric', 'min:0.01', 'max:24'],
            'date'          => ['required', 'date'],
            'activity_type' => ['nullable', 'string', 'max:255'],
            'description'   => ['nullable', 'string', 'max:2000'],
            'task_id'       => ['nullable', 'exists:tasks,id'],
        ];
    }
}
