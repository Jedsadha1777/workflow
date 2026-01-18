<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    protected $fillable = [
        'title',
        'content',
        'creator_id',
        'department_id',
        'template_document_id',
        'workflow_id',
        'form_data',
        'status',
        'submitted_at',
        'approved_at',
        'current_step',
    ];

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'content' => 'array',
            'form_data' => 'array',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(TemplateDocument::class, 'template_document_id');
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function approvers(): HasMany
    {
        return $this->hasMany(DocumentApprover::class)->orderBy('step_order');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(DocumentActivityLog::class)->orderBy('performed_at', 'desc');
    }

    public function isDraft(): bool
    {
        return $this->status === DocumentStatus::DRAFT;
    }

    public function isPending(): bool
    {
        return $this->status === DocumentStatus::PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === DocumentStatus::APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === DocumentStatus::REJECTED;
    }

    public function canEdit(): bool
    {
        return $this->isDraft() || $this->isRejected();
    }

    public function getCurrentApprover(): ?DocumentApprover
    {
        return $this->approvers()
            ->where('step_order', $this->current_step)
            ->first();
    }

    public function getNextApprover(): ?DocumentApprover
    {
        return $this->approvers()
            ->where('step_order', '>', $this->current_step)
            ->orderBy('step_order')
            ->first();
    }

    public function advanceToNextStep(): void
    {
        $nextApprover = $this->getNextApprover();

        if ($nextApprover) {
            $this->update(['current_step' => $nextApprover->step_order]);
        } else {
            $this->update([
                'status' => DocumentStatus::APPROVED,
                'approved_at' => now(),
            ]);
        }
    }
}
