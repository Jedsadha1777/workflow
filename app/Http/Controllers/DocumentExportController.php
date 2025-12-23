<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Support\Facades\Http;

class DocumentExportController extends Controller
{
    public function exportPdf(Document $document)
    {
        $content = $document->content;
        if (is_string($content)) {
            $content = json_decode($content, true);
        }

        $sheets = $content['sheets'] ?? [];
        $formData = $document->form_data ?? [];

        // คำนวณ scale และ orientation
        list($orientation, $scaleFactor) = self::calculateScale($sheets);

        foreach ($sheets as &$sheet) {
            $sheetHtml = $sheet['html'];
            $sheetName = $sheet['name'];

            // ลบ data-cell attributes
            $sheetHtml = preg_replace('/data-cell="[^"]*"/', '', $sheetHtml);

            // แทนค่า form data
            if (!empty($formData) && isset($formData[$sheetName])) {
                foreach ($formData[$sheetName] as $cell => $value) {
                    if (is_array($value) && isset($value['type']) && $value['type'] === 'signature') {
                        $approver = \App\Models\User::find($value['approver_id']);
                        $signatureHtml = $approver ? 
                            '<div style="border:2px solid #10b981;padding:6px;text-align:center;background:#f0fdf4;border-radius:4px;max-width:100%;box-sizing:border-box;overflow:hidden;word-wrap:break-word;">' .
                            '<div style="font-weight:bold;color:#065f46;font-size:9pt;">✓ ' . htmlspecialchars($approver->name) . '</div>' .
                            '<div style="font-size:8pt;color:#6b7280;margin-top:2px;">Signed: ' . date('Y-m-d H:i', strtotime($value['signed_at'])) . '</div>' .
                            '</div>' : '';
                        
                        $sheetHtml = preg_replace('/<td([^>]*)>.*?<\/td>/s', '<td$1>' . $signatureHtml . '</td>', $sheetHtml, 1);
                    } elseif (is_array($value) && isset($value['type']) && $value['type'] === 'date') {
                        $dateHtml = '<span style="padding:4px;display:block;font-weight:bold;font-size:9pt;">' . htmlspecialchars($value['date']) . '</span>';
                        $sheetHtml = preg_replace('/<td([^>]*)>.*?<\/td>/s', '<td$1>' . $dateHtml . '</td>', $sheetHtml, 1);
                    } else {
                        $escapedValue = htmlspecialchars($value);
                        $valueHtml = '<span style="padding:4px;display:block;font-weight:bold;font-size:9pt;">' . $escapedValue . '</span>';
                        $sheetHtml = preg_replace('/<td([^>]*)>.*?<\/td>/s', '<td$1>' . $valueHtml . '</td>', $sheetHtml, 1);
                    }
                }
            }

            // Scale HTML structure
            $sheetHtml = self::scaleHtmlStructure($sheetHtml, $scaleFactor);

            $sheet['html'] = $sheetHtml;
        }

        $html = view('pdf.document-puppeteer', [
            'document' => $document,
            'sheets' => $sheets,
            'orientation' => $orientation,
        ])->render();

        $pdfServiceUrl = config('services.pdf.url', 'http://localhost:3000');
        
        try {
            $response = Http::timeout(60)->post($pdfServiceUrl . '/api/generate-pdf', [
                'html' => $html,
                'options' => [
                    'format' => 'A4',
                    'orientation' => $orientation,
                    'marginTop' => '5mm',
                    'marginRight' => '5mm',
                    'marginBottom' => '5mm',
                    'marginLeft' => '5mm',
                    'printBackground' => true,
                ]
            ]);

            if ($response->successful()) {
                return response($response->body(), 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="document-' . $document->id . '.pdf"'
                ]);
            } else {
                \Log::error('PDF Service Error: ' . $response->body());
                return response()->json([
                    'error' => 'Failed to generate PDF',
                    'message' => 'PDF service returned error'
                ], 500);
            }
        } catch (\Exception $e) {
            \Log::error('PDF Service Connection Error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to connect to PDF service',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private static function calculateScale(array $sheets): array
    {
        $maxWidth = 0;
        
        foreach ($sheets as $sheet) {
            // รวม width จาก col attributes
            preg_match_all('/<col[^>]*width="(\d+)"/i', $sheet['html'], $colMatches);
            $colWidth = array_sum(array_map('intval', $colMatches[1]));
            
            // รวม width จาก style
            preg_match_all('/width:\s*(\d+)px/', $sheet['html'], $styleMatches);
            $styleWidth = array_sum(array_map('intval', $styleMatches[1]));
            
            $width = max($colWidth, $styleWidth);
            $maxWidth = max($maxWidth, $width);
        }
        
        // A4 usable width (หัก margin 10mm)
        $portraitWidth = 756;   // 794 - 38
        $landscapeWidth = 1085; // 1123 - 38
        
        if ($maxWidth <= $portraitWidth) {
            return ['portrait', 1.0];
        } elseif ($maxWidth <= $landscapeWidth) {
            return ['landscape', 1.0];
        } else {
            // ต้อง scale
            $orientation = 'landscape';
            $scaleFactor = $landscapeWidth / $maxWidth;
            return [$orientation, $scaleFactor];
        }
    }

    private static function scaleHtmlStructure(string $html, float $scale): string
    {
        if ($scale >= 0.99) {
            return $html;
        }

        // Scale <col width="..."> attributes
        $html = preg_replace_callback(
            '/<col([^>]*?)width="(\d+)"/i',
            function($m) use ($scale) {
                return '<col' . $m[1] . 'width="' . round($m[2] * $scale) . '"';
            },
            $html
        );

        // Scale <table width="..."> attributes
        $html = preg_replace_callback(
            '/<table([^>]*?)width="(\d+)"/i',
            function($m) use ($scale) {
                return '<table' . $m[1] . 'width="' . round($m[2] * $scale) . '"';
            },
            $html
        );

        // Scale width in style attributes
        $html = preg_replace_callback(
            '/(width):\s*(\d+)px/i',
            function($m) use ($scale) {
                return $m[1] . ': ' . round($m[2] * $scale) . 'px';
            },
            $html
        );

        // Scale height in style attributes
        $html = preg_replace_callback(
            '/(height):\s*(\d+)px/i',
            function($m) use ($scale) {
                return $m[1] . ': ' . round($m[2] * $scale) . 'px';
            },
            $html
        );

        // Scale left position
        $html = preg_replace_callback(
            '/(left):\s*(\d+)px/i',
            function($m) use ($scale) {
                return $m[1] . ': ' . round($m[2] * $scale) . 'px';
            },
            $html
        );

        // Scale right position
        $html = preg_replace_callback(
            '/(right):\s*(\d+)px/i',
            function($m) use ($scale) {
                return $m[1] . ': ' . round($m[2] * $scale) . 'px';
            },
            $html
        );

        // Scale top position
        $html = preg_replace_callback(
            '/(top):\s*(\d+)px/i',
            function($m) use ($scale) {
                return $m[1] . ': ' . round($m[2] * $scale) . 'px';
            },
            $html
        );

        // Scale margin-left
        $html = preg_replace_callback(
            '/(margin-left):\s*(\d+)px/i',
            function($m) use ($scale) {
                return $m[1] . ': ' . round($m[2] * $scale) . 'px';
            },
            $html
        );

        // Scale margin-right
        $html = preg_replace_callback(
            '/(margin-right):\s*(\d+)px/i',
            function($m) use ($scale) {
                return $m[1] . ': ' . round($m[2] * $scale) . 'px';
            },
            $html
        );

        // Scale translateX
        $html = preg_replace_callback(
            '/(translateX\()(\d+)(px\))/i',
            function($m) use ($scale) {
                return $m[1] . round($m[2] * $scale) . $m[3];
            },
            $html
        );

        // Scale translateY
        $html = preg_replace_callback(
            '/(translateY\()(\d+)(px\))/i',
            function($m) use ($scale) {
                return $m[1] . round($m[2] * $scale) . $m[3];
            },
            $html
        );

        // Scale font-size
        $html = preg_replace_callback(
            '/(font-size):\s*(\d+)(pt|px)/i',
            function($m) use ($scale) {
                return $m[1] . ': ' . max(6, round($m[2] * $scale)) . $m[3];
            },
            $html
        );

        // Scale padding
        $html = preg_replace_callback(
            '/(padding):\s*(\d+)px\s+(\d+)px/i',
            function($m) use ($scale) {
                return $m[1] . ': ' . round($m[2] * $scale) . 'px ' . round($m[3] * $scale) . 'px';
            },
            $html
        );

        // Scale border-spacing (ถ้ามี)
        $html = preg_replace_callback(
            '/(border-spacing):\s*(\d+)px/i',
            function($m) use ($scale) {
                return $m[1] . ': ' . round($m[2] * $scale) . 'px';
            },
            $html
        );

        // Remove cellpadding/cellspacing attributes และใส่ค่า 0
        $html = preg_replace('/cellspacing="[^"]*"/', 'cellspacing="0"', $html);
        $html = preg_replace('/cellpadding="[^"]*"/', 'cellpadding="0"', $html);

        return $html;
    }

    private static function detectOrientation(array $sheets): string
    {
        $maxWidth = 0;
        
        foreach ($sheets as $sheet) {
            preg_match_all('/width:\s*(\d+)px/', $sheet['html'], $matches);
            $width = array_sum(array_map('intval', $matches[1]));
            $maxWidth = max($maxWidth, $width);
        }
        
        return $maxWidth > 800 ? 'landscape' : 'portrait';
    }
}