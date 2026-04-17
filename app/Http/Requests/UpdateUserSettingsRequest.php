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
            'theme'                  => ['sometimes', 'string', 'in:dark,light,system'],
            'language'               => ['sometimes', 'string', 'max:10'],
            'date_format'            => ['sometimes', 'string', 'max:20'],
            'time_format'            => ['sometimes', 'string', 'in:12h,24h'],
            'notifications_enabled'  => ['sometimes', 'boolean'],
            'email_notifications'    => ['sometimes', 'boolean'],
            'push_notifications'     => ['sometimes', 'boolean'],
            'notification_sound'     => ['sometimes', 'boolean'],
            'desktop_notifications'  => ['sometimes', 'boolean'],
            'dnd_enabled'            => ['sometimes', 'boolean'],
            'dnd_start_time'         => ['sometimes', 'nullable', 'date_format:H:i'],
            'dnd_end_time'           => ['sometimes', 'nullable', 'date_format:H:i'],
            'enter_to_send'          => ['sometimes', 'boolean'],
            'show_typing_indicator'  => ['sometimes', 'boolean'],
            'show_read_receipts'     => ['sometimes', 'boolean'],
            'reduce_motion'          => ['sometimes', 'boolean'],
            'high_contrast'          => ['sometimes', 'boolean'],
            'font_size'              => ['sometimes', 'string', 'in:small,medium,large,xlarge'],
            'show_online_status'     => ['sometimes', 'boolean'],
            'allow_direct_messages'  => ['sometimes', 'string', 'in:everyone,team_members,nobody'],
        ];
    }
}
