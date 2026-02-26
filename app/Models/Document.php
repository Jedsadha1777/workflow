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
        'division_id',
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

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
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
        return in_array($this->status, [
            DocumentStatus::PREPARE,
            DocumentStatus::PENDING_CHECKING,
            DocumentStatus::CHECKING,
            DocumentStatus::PENDING,
        ]);
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

    public function canBeEditedBy(User $user): bool
    {
        if (!$this->canEdit()) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $this->creator_id === $user->id;
    }

    public function canBeRecalledBy(User $user): bool
    {
        if (!$this->isPending()) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $this->creator_id === $user->id;
    }

    public function getViewType(User $user): string
    {
        if ($user->isAdmin()) {
            return 'full';
        }

        if ($this->creator_id === $user->id) {
            return 'full';
        }

        // ถึง step ของเราและเอกสารยัง PENDING อยู่
        if ($this->isPending()) {
            $isCurrentApprover = $this->approvers()
                ->where('approver_id', $user->id)
                ->where('step_order', $this->current_step)
                ->exists();
        } else {
            $isCurrentApprover = false;
        }

        if ($isCurrentApprover) {
            return 'full';
        }

        // เคย approve ไปแล้ว
        $hasActed = $this->approvers()
             ->where('approver_id', $user->id)
            ->whereIn('status', [
                \App\Enums\ApprovalStatus::APPROVED,
                \App\Enums\ApprovalStatus::REJECTED,
            ])
             ->exists();
 
        if ($hasActed) {
            return 'full';
        }

        return 'none';
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

    public function recallToDraft(): void
    {
        $this->update([
            'status' => DocumentStatus::DRAFT,
            'submitted_at' => null,
        ]);

        $this->approvers()->update([
            'status' => \App\Enums\ApprovalStatus::PENDING,
            'approved_at' => null,
            'comment' => null,
        ]);
    }

    public function setSignature(string $sheet, string $cell, int $userId): void
    {
        $user = User::find($userId);
        if (!$user || !$user->signature_image) {
            return;
        }


        $formData = $this->form_data ?? [];
        if (!isset($formData[$sheet])) {
            $formData[$sheet] = [];
        }
        $formData[$sheet][$cell] = [
            'type' => 'signature',
            'approver_id' => $userId,
            'signed_at' => now()->toISOString(),
         ];
        $this->form_data = $formData;
    }

    public function setApprovedDate(string $sheet, string $cell): void
    {
        $formData = $this->form_data ?? [];
        if (!isset($formData[$sheet])) {
            $formData[$sheet] = [];
        }
        $formData[$sheet][$cell] = [
            'type' => 'date',
            'date' => now()->toDateString(),
        ];
        $this->form_data = $formData;
    }
}