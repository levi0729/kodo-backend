<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'            => ['required', 'string', 'max:255'],
            'slug'            => ['sometimes', 'string', 'max:100'],
            'description'     => ['nullable', 'string', 'max:2000'],
            'color'           => ['sometimes', 'string', 'max:20'],
            'icon'            => ['nullable', 'string', 'max:50'],
            'project_type'    => ['sometimes', 'string', 'in:kanban,list,timeline,calendar'],
            'status'          => ['sometimes', 'string', 'in:planning,active,on_hold,completed,archived'],
            'start_date'      => ['nullable', 'date'],
            'target_end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }
}
