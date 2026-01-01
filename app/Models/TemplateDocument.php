<?php

namespace App\Models;

use App\Services\TemplateFieldParser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;

class TemplateDocument extends Model
{
    protected $fillable = [
        'name',
        'excel_file',
        'pdf_layout_html',
        'pdf_orientation',
        'content',
        'calculation_scripts',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Accessor สำหรับ content (จัดการ JSON ซ้อน 2 ชั้น)
     */
    protected function content(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if (!is_string($value)) {
                    return $value;
                }
                
                // Decode ครั้งที่ 1
                $decoded = json_decode($value, true);
                
                // ถ้าได้ string → decode อีกครั้ง
                if (is_string($decoded)) {
                    $decoded = json_decode($decoded, true);
                }
                
                if (is_array($decoded)) {
                    return $decoded;
                }
                
                return $value;
            },
            set: fn ($value) => is_array($value) ? json_encode($value) : $value,
        );
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'template_document_id');
    }

    /**
     * ดึง form fields ทั้งหมด
     * Service รับผิดชอบ parse เอง Model แค่ส่งข้อมูล
     */
    public function getFormFields(): array
    {
        $parser = app(TemplateFieldParser::class);
        $sheets = $this->content['sheets'] ?? [];

        return $parser->parseAllSheets($sheets);
    }

    /**
     * ดึงเฉพาะ signature fields
     */
    public function getSignatureFields(): array
    {
        $parser = app(TemplateFieldParser::class);
        return $parser->filterByType($this->getFormFields(), 'signature');
    }

    /**
     * ดึงเฉพาะ date fields
     */
    public function getDateFields(): array
    {
        $parser = app(TemplateFieldParser::class);
        return $parser->filterByType($this->getFormFields(), 'date');
    }

    /**
     * ดึง fields ตาม type
     */
    public function getFieldsByType(string $type): array
    {
        $parser = app(TemplateFieldParser::class);
        
        if (!$parser->isValidType($type)) {
            return [];
        }

        return $parser->filterByType($this->getFormFields(), $type);
    }
}