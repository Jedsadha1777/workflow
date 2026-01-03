<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentApprover extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'step_order',
        'required_role',
        'same_department',
        'approver_id',
        'approver_name',
        'approver_email',
        'signature_cell',
        'approved_date_cell',
        'status',
        'comment',
        'approved_at',
        'rejected_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ApprovalStatus::class,
            'same_department' => 'boolean',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
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

    public function canBeChangedBy(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $this->approver_id === $user->id && $this->status === ApprovalStatus::PENDING;
    }

    public function approveWithSignature(): void
    {
        $this->update([
            'status' => ApprovalStatus::APPROVED,
            'approved_at' => now(),
        ]);

        if ($this->signature_cell) {
            [$sheet, $cell] = $this->parseSignatureCell();

            if ($sheet && $cell) {
                $this->document->setSignature($sheet, $cell, $this->approver_id);
                $this->document->save();
            }
        }

        if ($this->approved_date_cell) {
            [$sheet, $cell] = $this->parseDateCell();

            if ($sheet && $cell) {
                $this->document->setApprovedDate($sheet, $cell);
                $this->document->save();
            }
        }
    }

    protected function parseSignatureCell(): array
    {
        if (!$this->signature_cell || !str_contains($this->signature_cell, ':')) {
            return [null, null];
        }

        return explode(':', $this->signature_cell, 2);
    }

    protected function parseDateCell(): array
    {
        if (!$this->approved_date_cell || !str_contains($this->approved_date_cell, ':')) {
            return [null, null];
        }

        return explode(':', $this->approved_date_cell, 2);
    }

    public function getApproverDisplay(): string
    {
        return $this->approver_name ?? 'N/A';
    }

    public function getApproverEmailDisplay(): string
    {
        return $this->approver_email ?? 'N/A';
    }
}
