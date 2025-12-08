<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'content',
        'creator_id',
        'department_id',
        'status',
        'submitted_at',
        'approved_at',
        'current_step',
    ];

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
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

    public function approvers(): HasMany
    {
        return $this->hasMany(DocumentApprover::class)->orderBy('step_order');
    }

    public function isLocked(): bool
    {
        return $this->status !== DocumentStatus::DRAFT;
    }

    public function canBeEditedBy(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($this->creator_id !== $user->id) {
            return false;
        }

        return $this->status === DocumentStatus::DRAFT;
    }

    public function canBeViewedBy(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($this->creator_id === $user->id) {
            return true;
        }

        if ($this->approvers()->where('approver_id', $user->id)->exists()) {
            return true;
        }

        if ($this->department_id === $user->department_id) {
            return true;
        }

        return false;
    }

    public function getViewType(User $user): string
    {
        if ($user->isAdmin()) {
            return 'full';
        }

        if ($this->creator_id === $user->id) {
            return 'full';
        }

        if ($this->approvers()->where('approver_id', $user->id)->exists()) {
            return 'full';
        }

        if ($this->department_id === $user->department_id && $this->status === DocumentStatus::DRAFT) {
            return 'full';
        }

        if ($this->department_id === $user->department_id) {
            return 'status_only';
        }

        return 'none';
    }
}