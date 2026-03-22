<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'description',
        'color',
        'icon',
        'project_type',
        'status',
        'start_date',
        'target_end_date',
        'actual_end_date',
        'progress',
        'settings',
        'owner_id',
    ];

    protected function casts(): array
    {
        return [
            'settings'        => 'json',
            'start_date'      => 'date',
            'target_end_date' => 'date',
            'actual_end_date' => 'date',
            'progress'        => 'integer',
        ];
    }

    // ── Relationships ──────────────────────────────────────

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function teams()
    {
        return $this->hasMany(Team::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function timeEntries()
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function participants()
    {
        return $this->hasMany(Participant::class, 'entity_id')
            ->where('entity_type', 'project');
    }

    public function members()
    {
        return $this->hasManyThrough(
            User::class,
            Participant::class,
            'entity_id',
            'id',
            'id',
            'user_id'
        )->where('participants.entity_type', 'project');
    }
}
