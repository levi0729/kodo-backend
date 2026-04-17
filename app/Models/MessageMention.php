<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageMention extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'message_id',
        'mention_type',
        'mentioned_id',
    ];

    public function message()
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * The user that was mentioned (when mention_type = 'user').
     */
    public function mentioned()
    {
        return $this->belongsTo(User::class, 'mentioned_id');
    }
}
