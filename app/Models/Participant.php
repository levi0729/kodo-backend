<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Participant extends Model
{
    use HasFactory;

    protected $fillable = [
        'entity_type',
        'entity_id',
        'user_id',
        'role',
        'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
        ];
    }

    // ── Relationships ──────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Resolve the entity model manually because entity_type stores simple
     * strings like 'project', 'team' instead of fully-qualified class names
     * that Eloquent's morphTo() expects.
     */
    public function getEntityAttribute()
    {
        $map = [
            'project' => \App\Models\Project::class,
            'team'    => \App\Models\Team::class,
        ];

        $class = $map[$this->entity_type] ?? null;

        if (! $class || ! $this->entity_id) {
            return null;
        }

        return $class::find($this->entity_id);
    }
}
