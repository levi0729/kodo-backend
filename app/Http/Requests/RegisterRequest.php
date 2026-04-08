<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username'     => ['required', 'string', 'max:100', 'unique:users', 'regex:/^[a-zA-Z0-9._-]+$/'],
            'email'        => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password'     => ['required', 'string', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            'display_name' => ['sometimes', 'string', 'max:150'],
            'job_title'    => ['sometimes', 'string', 'max:100'],
            'phone_number' => ['required', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.regex' => 'Username may only contain letters, numbers, dots, hyphens, and underscores.',
        ];
    }
}
