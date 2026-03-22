<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'theme'                 => ['sometimes', 'string', 'in:dark,light,system'],
            'language'              => ['sometimes', 'string', 'max:10'],
            'notifications_enabled' => ['sometimes', 'boolean'],
            'email_notifications'   => ['sometimes', 'boolean'],
            'push_notifications'    => ['sometimes', 'boolean'],
            'show_online_status'    => ['sometimes', 'boolean'],
        ];
    }
}
