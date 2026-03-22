<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'project_id'  => ['nullable', 'integer', 'exists:projects,id'],
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'color'       => ['sometimes', 'string', 'max:20'],
            'visibility'  => ['sometimes', 'string', 'in:public,private,hidden'],
            'is_private'  => ['sometimes', 'boolean'],
            'password'    => ['required_if:is_private,true', 'nullable', 'string', 'min:6'],
        ];
    }
}
