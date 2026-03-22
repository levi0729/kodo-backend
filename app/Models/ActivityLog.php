<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    public $timestamps = true;

    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'action',
        'target_type',
        'target_id',
    ];

    // ── Relationships ──────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Resolve the target model manually because target_type stores simple
     * strings like 'project', 'team', 'task' instead of fully-qualified
     * class names that Eloquent's morphTo() expects.
     */
    public function getTargetAttribute()
    {
        $map = [
            'project' => \App\Models\Project::class,
            'team'    => \App\Models\Team::class,
            'task'    => \App\Models\Task::class,
        ];

        $class = $map[$this->target_type] ?? null;

        if (! $class || ! $this->target_id) {
            return null;
        }

        return $class::find($this->target_id);
    }
}
