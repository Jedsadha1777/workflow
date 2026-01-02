<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
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
        'template_document_id',
        'form_data',
        'status',
        'submitted_at',
        'approved_at',
        'current_step',
    ];

    protected $casts = [
            'status' => DocumentStatus::class,
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'content' => 'array',
            'form_data' => 'array',
    ];


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
        // Admin ห้ามแก้ไขเนื้อหาเอกสาร
        if ($user->isAdmin()) {
            return false;
        }

        // ต้องเป็นเจ้าของเอกสาร
        if ($this->creator_id !== $user->id) {
            return false;
        }

        // แก้ไขได้แค่ DRAFT
        return $this->status === DocumentStatus::DRAFT;
    }

    public function canBeRecalledBy(User $user): bool
    {
        // ต้องเป็นสถานะ PENDING
        if ($this->status !== DocumentStatus::PENDING) {
            return false;
        }

        // ต้องเป็นเจ้าของเอกสาร
        if ($this->creator_id !== $user->id) {
            return false;
        }

        // ต้องยังไม่มีใครอนุมัติเลย
        $hasApproved = $this->approvers()
            ->where('status', ApprovalStatus::APPROVED->value)
            ->exists();

        return !$hasApproved;
    }

    public function recallToDraft(): void
    {
        $this->update([
            'status' => DocumentStatus::DRAFT,
            'submitted_at' => null,
        ]);

        // Reset approvers status กลับเป็น PENDING
        $this->approvers()->update([
            'status' => ApprovalStatus::PENDING->value,
            'approved_at' => null,
            'rejected_at' => null,
            'comment' => null,
        ]);
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

    public function getFormValue(string $sheet, string $cell): mixed
    {
        return $this->form_data[$sheet][$cell] ?? null;
    }

    public function setFormValue(string $sheet, string $cell, mixed $value): void
    {
        $formData = $this->form_data ?? [];
        $formData[$sheet][$cell] = $value;
        $this->form_data = $formData;
    }

    public function setSignature(string $sheet, string $cell, int $approverId): void
    {
        $this->setFormValue($sheet, $cell, [
            'type' => 'signature',
            'approver_id' => $approverId,
            'signed_at' => now()->toDateTimeString(),
        ]);
    }

    public function setApprovedDate(string $sheet, string $cell): void
    {
        $this->setFormValue($sheet, $cell, now()->format('d/m/Y'));
    }

    public function getSignatureFields(): ?array
    {
        if (!$this->template) {
            return null;
        }
        
        // ใช้ฟังก์ชันจาก TemplateDocument ที่มีอยู่แล้ว
        // TemplateDocument::getFormFields() หา fields จาก HTML แล้ว
        return $this->template->getSignatureFields();
    }

    public function getDateFields(): ?array
    {
        if (!$this->template) {
            return null;
        }
        
        // ใช้ฟังก์ชันจาก TemplateDocument ที่มีอยู่แล้ว
        // TemplateDocument::getFormFields() หา fields จาก HTML แล้ว
        return $this->template->getDateFields();
    }

    private function numberToColumn(int $num): string
    {
        $col = '';
        while ($num >= 0) {
            $col = chr(65 + ($num % 26)) . $col;
            $num = intval($num / 26) - 1;
        }
        return $col;
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(DocumentActivityLog::class)->orderBy('performed_at', 'desc');
    }
}