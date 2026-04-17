<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConversationParticipant extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'role',
        'is_muted',
        'last_read_at',
        'last_read_message_id',
        'joined_at',
        'left_at',
    ];

    protected function casts(): array
    {
        return [
            'is_muted'     => 'boolean',
            'last_read_at' => 'datetime',
            'joined_at'    => 'datetime',
            'left_at'      => 'datetime',
        ];
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
