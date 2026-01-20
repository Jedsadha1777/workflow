<?php

namespace App\Models;

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
        'parent_id',
    ];

    protected function casts(): array
    {
        return [
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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Workflow::class, 'parent_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('step_order');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
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

        // Archive parent if published
        if ($this->parent_id) {
            static::where('id', $this->parent_id)
                ->where('status', 'PUBLISHED')
                ->update(['status' => 'ARCHIVED']);
        }

        // Archive any siblings that are published (same parent)
        if ($this->parent_id) {
            static::where('parent_id', $this->parent_id)
                ->where('id', '!=', $this->id)
                ->where('status', 'PUBLISHED')
                ->update(['status' => 'ARCHIVED']);
        }

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
        $newWorkflow = $this->replicate();
        $newWorkflow->version = $this->version + 1;
        $newWorkflow->status = 'DRAFT';
        $newWorkflow->parent_id = $this->id;

        // Check if current template is expired or has newer published version
        $currentTemplate = $this->template;
        if ($currentTemplate) {
            $newerTemplate = \App\Models\TemplateDocument::where('name', $currentTemplate->name)
                ->where('status', \App\Enums\TemplateStatus::PUBLISHED)
                ->where('id', '!=', $currentTemplate->id)
                ->where(function ($query) {
                    $query->whereNull('expired_at')
                        ->orWhere('expired_at', '>', now());
                })
                ->orderBy('version', 'desc')
                ->first();

            // If current template expired or newer exists, check if workflow positions match
            if ($newerTemplate && ($currentTemplate->isExpired() || $newerTemplate->version > $currentTemplate->version)) {
                if ($this->templateWorkflowsMatch($currentTemplate, $newerTemplate)) {
                    $newWorkflow->template_id = $newerTemplate->id;
                }
            }
        }

        $newWorkflow->save();

        foreach ($this->steps as $step) {
            $newStep = $step->replicate();
            $newStep->workflow_id = $newWorkflow->id;
            $newStep->save();
        }

        return $newWorkflow;
    }

    protected function templateWorkflowsMatch(TemplateDocument $oldTemplate, TemplateDocument $newTemplate): bool
    {
        $oldWorkflows = $oldTemplate->workflows()->orderBy('step_order')->get();
        $newWorkflows = $newTemplate->workflows()->orderBy('step_order')->get();

        if ($oldWorkflows->count() !== $newWorkflows->count()) {
            return false;
        }

        foreach ($oldWorkflows as $index => $oldWf) {
            $newWf = $newWorkflows[$index] ?? null;
            if (!$newWf) {
                return false;
            }

            if (
                $oldWf->signature_cell !== $newWf->signature_cell ||
                $oldWf->approved_date_cell !== $newWf->approved_date_cell
            ) {
                return false;
            }
        }

        return true;
    }

    public function getVersionHistoryAttribute()
    {
        $versions = collect([$this]);

        // Get all ancestors
        $current = $this;
        while ($current->parent) {
            $versions->push($current->parent);
            $current = $current->parent;
        }

        // Get all descendants from root
        $root = $versions->last();
        $allDescendants = $this->getAllDescendants($root);

        return $versions->merge($allDescendants)->unique('id')->sortByDesc('version')->values();
    }

    protected function getAllDescendants(Workflow $workflow): \Illuminate\Support\Collection
    {
        $descendants = collect();

        foreach ($workflow->children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($this->getAllDescendants($child));
        }

        return $descendants;
    }
}
