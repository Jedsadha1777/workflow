<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentApprover extends Model
{
    protected $fillable = [
        'document_id',
        'step_order',
        'role_id',
        'department_id',
        'step_type_id',
        'signature_cell',
        'approved_date_cell',
        'approver_id',
        'approver_name',
        'approver_email',
        'status',
        'comment',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ApprovalStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function stepType(): BelongsTo
    {
        return $this->belongsTo(WorkflowStepType::class, 'step_type_id');
    }

    public function isPending(): bool
    {
        return $this->status === ApprovalStatus::PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === ApprovalStatus::APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === ApprovalStatus::REJECTED;
    }

    public function approve(?string $comment = null): void
    {
        $this->update([
            'status' => ApprovalStatus::APPROVED,
            'comment' => $comment,
            'approved_at' => now(),
        ]);
    }

    public function reject(?string $comment = null): void
    {
        $this->update([
            'status' => ApprovalStatus::REJECTED,
            'comment' => $comment,
            'approved_at' => now(),
        ]);
    }

    public function getStepLabel(): string
    {
        $typeName = $this->stepType->name ?? 'Step';
        $roleName = $this->role->name ?? $this->approver_name ?? 'Unknown';
        
        return "{$typeName} by {$roleName}";
    }
}
