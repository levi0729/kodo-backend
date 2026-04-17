<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'logo_url',
        'domain',
        'settings',
        'allowed_email_domains',
        'plan_type',
        'max_members',
        'max_storage_gb',
    ];

    protected function casts(): array
    {
        return [
            'settings'              => 'array',
            'allowed_email_domains' => 'array',
        ];
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public function teams()
    {
        return $this->hasMany(Team::class);
    }
}
