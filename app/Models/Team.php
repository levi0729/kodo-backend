<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Team extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'project_id',
        'name',
        'slug',
        'description',
        'icon_url',
        'color',
        'visibility',
        'is_private',
        'password_hash',
        'is_archived',
        'owner_id',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected function casts(): array
    {
        return [
            'is_private'  => 'boolean',
            'is_archived' => 'boolean',
        ];
    }

    // ── Relationships ──────────────────────────────────────

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function participants()
    {
        return $this->hasMany(Participant::class, 'entity_id')
            ->where('entity_type', 'team');
    }
}
