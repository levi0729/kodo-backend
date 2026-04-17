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
            'receiver_id'             => ['required_without:team_id', 'nullable', 'integer', 'exists:users,id'],
            'team_id'                 => ['required_without:receiver_id', 'nullable', 'integer', 'exists:teams,id'],
            'message'                 => ['required_without:attachments', 'nullable', 'string', 'max:5000'],
            'attachments'             => ['nullable', 'array', 'max:10'],
            'attachments.*.file_name' => ['required_with:attachments', 'string', 'max:255'],
            'attachments.*.file_url'  => ['required_with:attachments', 'string', 'max:500'],
            'attachments.*.file_type' => ['nullable', 'string', 'max:100'],
            'attachments.*.file_size' => ['nullable', 'integer', 'min:0'],
            'attachments.*.width'     => ['nullable', 'integer', 'min:0'],
            'attachments.*.height'    => ['nullable', 'integer', 'min:0'],
        ];
    }
}
