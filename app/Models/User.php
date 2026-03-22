<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'username',
        'email',
        'password',
        'display_name',
        'job_title',
        'department',
        'phone_number',
        'avatar_url',
        'cover_image_url',
        'bio',
        'timezone',
        'locale',
        'presence_status',
        'presence_message',
        'last_seen_at',
        'is_active',
        'failed_login_attempts',
        'locked_until',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_seen_at'      => 'datetime',
            'presence_expiry'   => 'datetime',
            'is_active'         => 'boolean',
            'is_verified'       => 'boolean',
            'is_admin'              => 'boolean',
            'failed_login_attempts' => 'integer',
            'locked_until'          => 'datetime',
            'password'              => 'hashed',
        ];
    }

    // ── Relationships ──────────────────────────────────────

    public function ownedProjects()
    {
        return $this->hasMany(Project::class, 'owner_id');
    }

    public function ownedTeams()
    {
        return $this->hasMany(Team::class, 'owner_id');
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function timeEntries()
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function settings()
    {
        return $this->hasOne(UserSetting::class);
    }

    public function sentMessages()
    {
        return $this->hasMany(ChatRoom::class, 'sender_id');
    }

    public function receivedMessages()
    {
        return $this->hasMany(ChatRoom::class, 'receiver_id');
    }

    public function participations()
    {
        return $this->hasMany(Participant::class);
    }

    public function friendsInitiated()
    {
        return $this->hasMany(Friend::class, 'user_id_1');
    }

    public function friendsReceived()
    {
        return $this->hasMany(Friend::class, 'user_id_2');
    }

    public function friends()
    {
        $initiated = $this->friendsInitiated()
            ->where('status', 'accepted')
            ->with('userTwo')
            ->get()
            ->pluck('userTwo');

        $received = $this->friendsReceived()
            ->where('status', 'accepted')
            ->with('userOne')
            ->get()
            ->pluck('userOne');

        return $initiated->merge($received);
    }
}
