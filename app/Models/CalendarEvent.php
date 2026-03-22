<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CalendarEvent extends Model
{
    use HasFactory;

    protected $table = 'calendar_events';

    protected $fillable = [
        'team_id',
        'organizer_id',
        'title',
        'description',
        'location',
        'is_online_meeting',
        'meeting_url',
        'start_time',
        'end_time',
        'is_all_day',
        'recurrence_rule',
        'status',
        'reminder_minutes',
        'color',
    ];

    protected function casts(): array
    {
        return [
            'start_time'        => 'datetime',
            'end_time'          => 'datetime',
            'is_online_meeting' => 'boolean',
            'is_all_day'        => 'boolean',
            'reminder_minutes'  => 'integer',
        ];
    }

    // ── Relationships ──────────────────────────────────────

    public function organizer()
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function attendees()
    {
        return $this->belongsToMany(User::class, 'event_attendees', 'event_id', 'user_id')
            ->withPivot('response_status');
    }
}
