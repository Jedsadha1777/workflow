<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowStepType extends Model
{
    protected $fillable = [
        'name',
        'code',
        'send_email',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'send_email' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function workflowSteps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class, 'step_type_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function isInUse(): bool
    {
        return $this->workflowSteps()->exists();
    }

    public function getUsageCount(): int
    {
        return $this->workflowSteps()->count();
    }
}
