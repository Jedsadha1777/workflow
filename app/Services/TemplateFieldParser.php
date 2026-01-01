<?php

namespace App\Services;

/**
 * Service สำหรับ parse template fields
 */
class TemplateFieldParser
{
    private const SUPPORTED_TYPES = [
        'text', 'email', 'tel', 'number', 'date',
        'textarea', 'select', 'checkbox', 'signature',
    ];

    /**
     * Parse ทุก sheets พร้อมกัน
     */
    public function parseAllSheets(array $sheets): array
    {
        $allFields = [];

        foreach ($sheets as $sheet) {
            $sheetName = $sheet['name'] ?? 'Unknown';
            $html = $sheet['html'] ?? '';

            $fields = $this->parseFields($html, $sheetName);
            $allFields = array_merge($allFields, $fields);
        }

        return $allFields;
    }

    /**
     * Parse HTML เพื่อหา form fields
     */
    public function parseFields(string $html, string $sheetName): array
    {
        return $this->parseFieldsWithRegex($html, $sheetName);
    }

    /**
     * Filter fields ตาม type
     */
    public function filterByType(array $fields, string $type): array
    {
        return array_filter($fields, fn($field) => $field['type'] === $type);
    }

    /**
     * Validate type
     */
    public function isValidType(string $type): bool
    {
        return in_array($type, self::SUPPORTED_TYPES);
    }

    /**
     * Get supported types
     */
    public function getSupportedTypes(): array
    {
        return self::SUPPORTED_TYPES;
    }


    /**
     * Regex-based implementation
     */
    private function parseFieldsWithRegex(string $html, string $sheetName): array
    {
        $fields = [];
        $pattern = $this->buildRegexPattern();
        
        preg_match_all($pattern, $html, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $field = $this->buildFieldFromMatch($match, $sheetName);
            
            if ($field) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * Build regex pattern with named capture groups
     * Pattern: [type* name cell="value"]
     */
    private function buildRegexPattern(): string
    {
        $types = implode('|', self::SUPPORTED_TYPES);
        
        return sprintf(
            '/\[(?P<type>%s)(?P<required>\*?)\s+(?P<name>[^\s\]]+)(?:\s+cell="(?P<cell>[^"]+)")?\]/',
            $types
        );
    }

    /**
     * Build field array from regex match
    */
    private function buildFieldFromMatch(array $match, string $sheetName): ?array
    {
        // Validate required fields
        if (!isset($match['type'], $match['name'])) {
            return null;
        }

        return [
            'sheet' => $sheetName,
            'type' => $match['type'],
            'required' => $match['required'] === '*',
            'name' => $match['name'],
            'cell' => $match['cell'] ?? null,
        ];
    }
}