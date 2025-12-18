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
        'signature_cell',
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

    // บันทึก signature เมื่อ approve
    public function approveWithSignature(): void
    {
        $this->update([
            'status' => ApprovalStatus::APPROVED,
            'approved_at' => now(),
        ]);

        // บันทึก signature ลง form_data ถ้ามี signature_cell
        if ($this->signature_cell) {
            [$sheet, $cell] = $this->parseSignatureCell();
            
            if ($sheet && $cell) {
                $this->document->setSignature($sheet, $cell, $this->approver_id);
                $this->document->save();
            }
        }
    }

    // แปลง signature_cell จาก "Sheet1:A5" เป็น ['Sheet1', 'A5']
    protected function parseSignatureCell(): array
    {
        if (!$this->signature_cell || !str_contains($this->signature_cell, ':')) {
            return [null, null];
        }

        return explode(':', $this->signature_cell, 2);
    }
}