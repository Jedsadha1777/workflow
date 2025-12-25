<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TemplateDocument extends Model
{
    protected $fillable = [
        'name',
        'excel_file',
        'content',
        'calculation_scripts',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'content' => 'array',
    ];

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'template_document_id');
    }

    public function getFormFields(): array
    {
        $fields = [];
        $sheets = $this->content['sheets'] ?? [];

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