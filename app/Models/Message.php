<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use HasFactory, SoftDeletes;

    const UPDATED_AT = null;

    protected $fillable = [
        'channel_id',
        'conversation_id',
        'parent_message_id',
        'sender_id',
        'content',
        'content_type',
        'formatted_content',
        'has_attachments',
        'is_pinned',
        'is_announcement',
        'is_edited',
        'edited_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'formatted_content' => 'array',
            'metadata'          => 'array',
            'has_attachments'   => 'boolean',
            'is_pinned'         => 'boolean',
            'is_announcement'   => 'boolean',
            'is_edited'         => 'boolean',
            'edited_at'         => 'datetime',
        ];
    }

    public function channel()
    {
        return $this->belongsTo(Channel::class);
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function parent()
    {
        return $this->belongsTo(Message::class, 'parent_message_id');
    }

    public function replies()
    {
        return $this->hasMany(Message::class, 'parent_message_id');
    }

    public function attachments()
    {
        return $this->hasMany(MessageAttachment::class);
    }

    public function reactions()
    {
        return $this->hasMany(MessageReaction::class);
    }

    public function mentions()
    {
        return $this->hasMany(MessageMention::class);
    }
}
