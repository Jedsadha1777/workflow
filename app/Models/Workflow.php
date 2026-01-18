<?php

namespace App\Models;

use App\Enums\TemplateStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workflow extends Model
{
    protected $fillable = [
        'name',
        'template_id',
        'department_id',
        'role_id',
        'version',
        'status',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(TemplateDocument::class, 'template_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('step_order');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'PUBLISHED');
    }

    public function canEdit(): bool
    {
        return $this->status === 'DRAFT';
    }

    public function canDelete(): bool
    {
        return $this->status === 'DRAFT' && $this->documents()->count() === 0;
    }

    public function canPublish(): bool
    {
        return $this->status === 'DRAFT' && $this->steps()->count() > 0;
    }

    public function canArchive(): bool
    {
        return $this->status === 'PUBLISHED';
    }

    public function publish(): bool
    {
        if (!$this->canPublish()) {
            return false;
        }

        // Archive other published versions of the same workflow (same name, template, department, role)
        static::where('name', $this->name)
            ->where('template_id', $this->template_id)
            ->where('department_id', $this->department_id)
            ->where('role_id', $this->role_id)
            ->where('id', '!=', $this->id)
            ->where('status', 'PUBLISHED')
            ->update(['status' => 'ARCHIVED']);

        $this->status = 'PUBLISHED';
        return $this->save();
    }

    public function archive(): bool
    {
        if (!$this->canArchive()) {
            return false;
        }

        $this->status = 'ARCHIVED';
        return $this->save();
    }

    public function createNewVersion(): static
    {
        // Find max version for this workflow group
        $maxVersion = static::where('name', $this->name)
            ->where('template_id', $this->template_id)
            ->where('department_id', $this->department_id)
            ->where('role_id', $this->role_id)
            ->max('version');

        // Clone workflow
        $newWorkflow = $this->replicate();
        $newWorkflow->version = ($maxVersion ?? 0) + 1;
        $newWorkflow->status = 'DRAFT';
        $newWorkflow->save();

        // Clone steps
        foreach ($this->steps as $step) {
            $newStep = $step->replicate();
            $newStep->workflow_id = $newWorkflow->id;
            $newStep->save();
        }

        return $newWorkflow;
    }

    public function getVersionHistoryAttribute()
    {
        return static::where('name', $this->name)
            ->where('template_id', $this->template_id)
            ->where('department_id', $this->department_id)
            ->where('role_id', $this->role_id)
            ->orderBy('version', 'desc')
            ->get();
    }
}
