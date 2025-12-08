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
        'approver_id',
        'step_order',
        'status',
        'comment',
        'approved_at',
        'rejected_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ApprovalStatus::class,
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
}