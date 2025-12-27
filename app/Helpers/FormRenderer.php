<?php

namespace App\Helpers;

use App\Models\Document;
use App\Models\TemplateDocument;

class FormRenderer
{
    public static function render(TemplateDocument $template, ?Document $document = null): string
    {
        $sheets = $template->content['sheets'] ?? [];
        $formData = $document->form_data ?? [];
        
        $html = '<div class="form-sheets space-y-6">';
        
        foreach ($sheets as $index => $sheet) {
            $sheetName = $sheet['name'];
            $sheetHtml = $sheet['html'];
            
            $html .= '<div class="sheet-form" data-sheet="' . htmlspecialchars($sheetName) . '">';
            $html .= '<h3 class="font-semibold text-lg mb-4">' . htmlspecialchars($sheetName) . '</h3>';
            
            // Convert template HTML to editable form
            $formHtml = self::convertToForm($sheetHtml, $sheetName, $formData[$sheetName] ?? []);
            
            $html .= '<div class="border rounded-lg p-4">' . $formHtml . '</div>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    protected static function convertToForm(string $html, string $sheetName, array $sheetData): string
    {
        // Replace [text ...] with actual input
        $html = preg_replace_callback(
            '/\[text(\*?)\s+([^\s\]]+)(?:\s+cell="([^"]+)")?\]/',
            function($matches) use ($sheetName, $sheetData) {
                $required = $matches[1] === '*';
                $name = $matches[2];
                $cell = $matches[3] ?? '';
                $value = $sheetData[$cell] ?? '';
                
                return '<input type="text" name="form_data[' . $sheetName . '][' . $cell . ']" value="' . htmlspecialchars($value) . '" ' . ($required ? 'required' : '') . ' class="form-input" />';
            },
            $html
        );
        
        // Similar replacements for other field types...
        
        return $html;
    }
}