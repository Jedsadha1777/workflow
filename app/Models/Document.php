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

    public function getFormValue(string $sheet, string $cell): mixed
    {
        return $this->form_data[$sheet][$cell] ?? null;
    }

    public function setFormValue(string $sheet, string $cell, mixed $value): void
    {
        $formData = $this->form_data ?? [];
        
        if (!isset($formData[$sheet])) {
            $formData[$sheet] = [];
        }
        
        $formData[$sheet][$cell] = $value;
        $this->form_data = $formData;
    }

    public function getSignatureData(string $sheet, string $cell): ?array
    {
        $value = $this->getFormValue($sheet, $cell);
        
        if (is_array($value) && isset($value['type']) && $value['type'] === 'signature') {
            return $value;
        }
        
        return null;
    }

    public function setSignature(string $sheet, string $cell, int $approverId, ?string $signedAt = null): void
    {
        $this->setFormValue($sheet, $cell, [
            'type' => 'signature',
            'approver_id' => $approverId,
            'signed_at' => $signedAt ?? now()->toISOString(),
        ]);
    }

    public function setApprovedDate(string $sheet, string $cell, ?string $date = null): void
    {
        $this->setFormValue($sheet, $cell, [
            'type' => 'date',
            'date' => $date ?? now()->format('Y-m-d H:i:s'),
        ]);
    }

    public function renderWithData(): string
    {
        if (!$this->content) {
            return '';
        }

        $html = '';
        $sheets = $this->content['sheets'] ?? [];

        foreach ($sheets as $sheet) {
            $sheetHtml = $sheet['html'];
            $sheetName = $sheet['name'];
            $formData = $this->form_data[$sheetName] ?? [];

            foreach ($formData as $cell => $value) {
                if (is_array($value) && isset($value['type']) && $value['type'] === 'signature') {
                    $approver = User::find($value['approver_id']);
                    $signatureHtml = $approver ? 
                        '<div style="border:2px solid #10b981;padding:10px;text-align:center;background:#f0fdf4;border-radius:6px;">' .
                        '<div style="font-weight:bold;">' . htmlspecialchars($approver->name) . '</div>' .
                        '<div style="font-size:12px;color:#6b7280;">Signed at: ' . $value['signed_at'] . '</div>' .
                        '</div>' : 
                        '[Signature Pending]';
                    
                    $sheetHtml = str_replace('[signature cell="' . $cell . '"]', $signatureHtml, $sheetHtml);
                } else {
                    $sheetHtml = preg_replace(
                        '/\[(?:text|email|tel|number|date|textarea|select|checkbox)\s+[^\]]+cell="' . preg_quote($cell) . '"[^\]]*\]/',
                        htmlspecialchars($value),
                        $sheetHtml
                    );
                }
            }

            $html .= $sheetHtml;
        }

        return $html;
    }

    public function getFormFields(): array
    {
        $fields = [];
        $content = is_string($this->content) ? json_decode($this->content, true) : $this->content;
        $sheets = $content['sheets'] ?? [];

        foreach ($sheets as $sheet) {
            $sheetName = $sheet['name'];
            $html = $sheet['html'];

            preg_match_all('/\[(text|email|tel|number|date|textarea|select|checkbox|signature)(\*?)\s+([^\s\]]+)(?:\s+cell="([^"]+)")?\]/', $html, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $fieldType = $match[1];
                $required = $match[2] === '*';
                $fieldName = $match[3];
                $cell = $match[4] ?? null;

                $fields[] = [
                    'sheet' => $sheetName,
                    'type' => $fieldType,
                    'name' => $fieldName,
                    'cell' => $cell,
                    'required' => $required,
                ];
            }
        }

        return $fields;
    }

    public function getSignatureFields(): array
    {
        return array_filter($this->getFormFields(), fn($field) => $field['type'] === 'signature');
    }

    public function getDateFields(): array
    {
        return array_filter($this->getFormFields(), fn($field) => $field['type'] === 'date');
    }
}