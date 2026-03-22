<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatRoom extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id',
        'sender_id',
        'receiver_id',
        'message',
        'is_read',
        'is_deleted',
        'deleted_at',
        'read_at',
        'sent_at',
        'is_pinned',
    ];

    protected function casts(): array
    {
        return [
            'is_read'    => 'boolean',
            'is_deleted' => 'boolean',
            'is_pinned'  => 'boolean',
            'deleted_at' => 'datetime',
            'read_at'    => 'datetime',
            'sent_at'    => 'datetime',
        ];
    }

    // ── Relationships ──────────────────────────────────────

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function room()
    {
        return $this->belongsTo(ChatRoom::class, 'room_id');
    }
}
