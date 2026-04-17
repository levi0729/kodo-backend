<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventAttendee extends Model
{
    public $timestamps = false;

    protected $table = 'event_attendees';

    protected $fillable = [
        'event_id',
        'user_id',
        'response_status',
        'is_required',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'is_required'  => 'boolean',
            'responded_at' => 'datetime',
        ];
    }

    public function event()
    {
        return $this->belongsTo(CalendarEvent::class, 'event_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
