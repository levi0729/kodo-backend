<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Channel extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'team_id',
        'name',
        'slug',
        'description',
        'channel_type',
        'is_default',
        'allow_threads',
        'allow_reactions',
        'allow_mentions',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_default'      => 'boolean',
            'allow_threads'   => 'boolean',
            'allow_reactions' => 'boolean',
            'allow_mentions'  => 'boolean',
        ];
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }
}
