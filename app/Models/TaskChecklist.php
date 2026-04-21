<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskChecklist extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'task_checklists';

    protected $fillable = [
        'task_id',
        'title',
        'position',
    ];

    // ── Relationships ──────────────────────────────────────

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function items()
    {
        return $this->hasMany(TaskChecklistItem::class, 'checklist_id')
            ->orderBy('position')
            ->orderBy('id');
    }
}
