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
        'notifications_enabled',
        'email_notifications',
        'push_notifications',
        'show_online_status',
    ];

    protected function casts(): array
    {
        return [
            'notifications_enabled' => 'boolean',
            'email_notifications'   => 'boolean',
            'push_notifications'    => 'boolean',
            'show_online_status'    => 'boolean',
        ];
    }

    // ── Relationships ──────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
