<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'team_id',
        'bucket_id',
        'parent_task_id',
        'title',
        'description',
        'status',
        'priority',
        'start_date',
        'due_date',
        'completed_at',
        'estimated_hours',
        'actual_hours',
        'progress',
        'position',
        'labels',
        'metadata',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'labels'          => 'json',
            'metadata'        => 'json',
            'start_date'      => 'date',
            'due_date'        => 'date',
            'completed_at'    => 'datetime',
            'estimated_hours' => 'decimal:2',
            'actual_hours'    => 'decimal:2',
            'progress'        => 'integer',
        ];
    }

    // ── Relationships ──────────────────────────────────────

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignees()
    {
        return $this->belongsToMany(User::class, 'task_assignees')
            ->withPivot('assigned_at', 'assigned_by');
    }

    public function parentTask()
    {
        return $this->belongsTo(Task::class, 'parent_task_id');
    }

    public function subtasks()
    {
        return $this->hasMany(Task::class, 'parent_task_id');
    }

    public function checklists()
    {
        return $this->hasMany(TaskChecklist::class)
            ->orderBy('position')
            ->orderBy('id');
    }
}
