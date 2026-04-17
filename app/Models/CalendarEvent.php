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
        'channel_id',
        'organizer_id',
        'title',
        'description',
        'location',
        'is_online_meeting',
        'meeting_url',
        'meeting_id',
        'start_time',
        'end_time',
        'is_all_day',
        'timezone',
        'is_recurring',
        'recurrence_rule',
        'recurrence_end_date',
        'parent_event_id',
        'status',
        'reminder_minutes',
        'color',
        'category',
    ];

    protected function casts(): array
    {
        return [
            'start_time'         => 'datetime',
            'end_time'           => 'datetime',
            'is_online_meeting'  => 'boolean',
            'is_all_day'         => 'boolean',
            'is_recurring'       => 'boolean',
            'recurrence_end_date'=> 'date',
            'reminder_minutes'   => 'integer',
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
            ->withPivot('response_status', 'is_required', 'responded_at');
    }
}
