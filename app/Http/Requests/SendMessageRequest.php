<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'receiver_id' => ['required_without:team_id', 'nullable', 'integer', 'exists:users,id'],
            'team_id'     => ['required_without:receiver_id', 'nullable', 'integer', 'exists:teams,id'],
            'message'     => ['required', 'string', 'min:1', 'max:5000'],
        ];
    }
}
