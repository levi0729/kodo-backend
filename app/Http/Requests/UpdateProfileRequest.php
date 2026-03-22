<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username'     => ['sometimes', 'string', 'max:100', Rule::unique('users')->ignore($this->user()?->id)],
            'display_name' => ['sometimes', 'string', 'max:150'],
            'job_title'    => ['sometimes', 'string', 'max:100'],
            'department'   => ['sometimes', 'string', 'max:100'],
            'avatar_url'   => ['nullable', 'string', 'max:500'],
            'bio'          => ['nullable', 'string', 'max:1000'],
            'email'        => [
                'sometimes', 'string', 'email', 'max:255',
                Rule::unique('users')->ignore($this->user()?->id),
            ],
        ];
    }
}
