<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatRoomAttachment extends Model
{
    use HasFactory;

    protected $table = 'chat_room_attachments';

    public $timestamps = false;

    protected $fillable = [
        'chat_room_id',
        'uploaded_by',
        'file_name',
        'file_type',
        'file_size',
        'file_url',
        'width',
        'height',
    ];

    protected function casts(): array
    {
        return [
            'file_size'  => 'integer',
            'width'      => 'integer',
            'height'     => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function chatRoom()
    {
        return $this->belongsTo(ChatRoom::class, 'chat_room_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
