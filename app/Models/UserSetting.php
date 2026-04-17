<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'theme',
        'language',
        'date_format',
        'time_format',
        'notifications_enabled',
        'email_notifications',
        'push_notifications',
        'notification_sound',
        'desktop_notifications',
        'dnd_enabled',
        'dnd_start_time',
        'dnd_end_time',
        'enter_to_send',
        'show_typing_indicator',
        'show_read_receipts',
        'reduce_motion',
        'high_contrast',
        'font_size',
        'show_online_status',
        'allow_direct_messages',
    ];

    protected function casts(): array
    {
        return [
            'notifications_enabled'  => 'boolean',
            'email_notifications'    => 'boolean',
            'push_notifications'     => 'boolean',
            'notification_sound'     => 'boolean',
            'desktop_notifications'  => 'boolean',
            'dnd_enabled'            => 'boolean',
            'enter_to_send'          => 'boolean',
            'show_typing_indicator'  => 'boolean',
            'show_read_receipts'     => 'boolean',
            'reduce_motion'          => 'boolean',
            'high_contrast'          => 'boolean',
            'show_online_status'     => 'boolean',
        ];
    }

    // ── Relationships ──────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
