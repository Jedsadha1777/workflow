<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = [
        'name',
        'code',
        'is_admin',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_admin' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function workflowSteps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeNonAdmin($query)
    {
        return $query->where('is_admin', false);
    }

    public function scopeForWorkflow($query)
    {
        return $query->active()->nonAdmin();
    }

    public function isInUse(): bool
    {
        return $this->users()->exists() || $this->workflowSteps()->exists();
    }
}
