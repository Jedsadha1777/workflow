<?php

namespace App\Services;

class CalculationService
{
    public static function executeCalculations(array $formData, string $calculationScripts): array
    {
        if (empty($calculationScripts)) {
            return $formData;
        }

        \Log::info('Starting calculations', ['scripts' => $calculationScripts]);

        // แปลง JavaScript const เป็น PHP $variable
        $phpCode = preg_replace('/const\s+(\w+)\s*=/', '$$1 =', $calculationScripts);
        
        // แปลง getValue() เป็นค่าจริง
        $phpCode = preg_replace_callback(
            '/getValue\s*\(\s*["\']([^"\']+)["\']\s*\)/',
            function($m) use ($formData) {
                $cellRef = $m[1];
                $parts = explode(':', $cellRef);
                if (count($parts) !== 2) {
                    return '0';
                }
                [$sheet, $cell] = $parts;
                $value = $formData[$sheet][$cell] ?? '';
                return is_numeric($value) ? $value : '0';
            },
            $phpCode
        );
        
        // แปลง parseFloat() เป็น floatval()
        $phpCode = preg_replace('/parseFloat\s*\(\s*([^)]+)\s*\)/', 'floatval($1)', $phpCode);
        
        // แปลง || 0 เป็น ?: 0
        $phpCode = str_replace('|| 0', '?: 0', $phpCode);
        
        // แปลง setValue() เป็น array assignment
        $phpCode = preg_replace_callback(
            '/setValue\s*\(\s*["\']([^"\']+)["\']\s*,\s*(.+?)\s*\)\s*;/',
            function($m) {
                $cellRef = $m[1];
                $expression = $m[2];
                
                // เพิ่ม $ หน้าตัวแปรใน expression
                $expression = preg_replace('/\b([a-zA-Z_]\w*)\b/', '$$1', $expression);
                
                $parts = explode(':', $cellRef);
                if (count($parts) !== 2) {
                    return '';
                }
                [$sheet, $cell] = $parts;
                return "\$formData['" . addslashes($sheet) . "']['" . addslashes($cell) . "'] = $expression;";
            },
            $phpCode
        );
        
        \Log::info('Converted PHP code', ['code' => $phpCode]);
        
        try {
            eval($phpCode);
        } catch (\Throwable $e) {
            \Log::error('Calculation error', ['error' => $e->getMessage(), 'code' => $phpCode]);
        }

        \Log::info('Calculations complete');

        return $formData;
    }
}