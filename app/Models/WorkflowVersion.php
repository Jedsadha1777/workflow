<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowVersion extends Model
{
    protected $fillable = [
        'workflow_id',
        'template_id',
        'version',
        'status',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(TemplateDocument::class, 'template_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('step_order');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'DRAFT');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'PUBLISHED');
    }

    public function scopeArchived($query)
    {
        return $query->where('status', 'ARCHIVED');
    }

    public function isDraft(): bool
    {
        return $this->status === 'DRAFT';
    }

    public function isPublished(): bool
    {
        return $this->status === 'PUBLISHED';
    }

    public function isArchived(): bool
    {
        return $this->status === 'ARCHIVED';
    }

    public function canEdit(): bool
    {
        return $this->isDraft();
    }

    public function canPublish(): bool
    {
        return $this->isDraft() && $this->steps()->exists();
    }

    public function canArchive(): bool
    {
        return $this->isPublished();
    }

    public function publish(): bool
    {
        if (!$this->canPublish()) {
            return false;
        }

        // Archive other published versions of the same workflow + template
        WorkflowVersion::where('workflow_id', $this->workflow_id)
            ->where('template_id', $this->template_id)
            ->where('id', '!=', $this->id)
            ->where('status', 'PUBLISHED')
            ->update(['status' => 'ARCHIVED']);

        $this->update(['status' => 'PUBLISHED']);
        return true;
    }

    public function archive(): bool
    {
        if (!$this->canArchive()) {
            return false;
        }

        $this->update(['status' => 'ARCHIVED']);
        return true;
    }

    public function cloneToNewVersion(): WorkflowVersion
    {
        $newVersion = $this->workflow->createNewVersion($this->template_id);

        foreach ($this->steps as $step) {
            $newVersion->steps()->create([
                'step_order' => $step->step_order,
                'role_id' => $step->role_id,
                'step_type_id' => $step->step_type_id,
                'same_department' => $step->same_department,
                'send_email' => $step->send_email,
                'signature_cell' => $step->signature_cell,
                'approved_date_cell' => $step->approved_date_cell,
            ]);
        }

        return $newVersion;
    }

    public function isInUse(): bool
    {
        return $this->documents()->exists();
    }

    public function getDisplayName(): string
    {
        return $this->workflow->name . ' v' . $this->version;
    }
}
