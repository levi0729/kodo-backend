<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskChecklistItem extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'task_checklist_items';

    protected $fillable = [
        'checklist_id',
        'title',
        'is_completed',
        'completed_at',
        'completed_by',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'is_completed' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    // ── Relationships ──────────────────────────────────────

    public function checklist()
    {
        return $this->belongsTo(TaskChecklist::class, 'checklist_id');
    }

    public function completedByUser()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
