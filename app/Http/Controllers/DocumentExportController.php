<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\IOFactory;

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

        $orientation = self::detectOrientation($sheets);

        foreach ($sheets as &$sheet) {
            $sheetHtml = $sheet['html'];
            $sheetName = $sheet['name'];

            if (!empty($formData) && isset($formData[$sheetName])) {
                foreach ($formData[$sheetName] as $cell => $value) {
                    if (is_array($value) && isset($value['type']) && $value['type'] === 'signature') {
                        $approver = \App\Models\User::find($value['approver_id']);
                        $signatureHtml = $approver ? 
                            '<div style="border:2px solid #10b981;padding:10px;text-align:center;background:#f0fdf4;border-radius:6px;">' .
                            '<div style="font-weight:bold;color:#065f46;">✓ ' . htmlspecialchars($approver->name) . '</div>' .
                            '<div style="font-size:11px;color:#6b7280;margin-top:4px;">Signed: ' . date('Y-m-d H:i', strtotime($value['signed_at'])) . '</div>' .
                            '</div>' : '';
                        
                        $sheetHtml = preg_replace(
                            '/<td([^>]*data-cell="' . preg_quote($sheetName . ':' . $cell, '/') . '"[^>]*)>.*?<\/td>/s',
                            '<td$1>' . $signatureHtml . '</td>',
                            $sheetHtml
                        );
                    } elseif (is_array($value) && isset($value['type']) && $value['type'] === 'date') {
                        $dateHtml = '<div style="padding:4px;"><strong>' . htmlspecialchars($value['date']) . '</strong></div>';
                        
                        $sheetHtml = preg_replace(
                            '/<td([^>]*data-cell="' . preg_quote($sheetName . ':' . $cell, '/') . '"[^>]*)>.*?<\/td>/s',
                            '<td$1>' . $dateHtml . '</td>',
                            $sheetHtml
                        );
                    } else {
                        $escapedValue = htmlspecialchars($value);
                        $sheetHtml = preg_replace_callback(
                            '/<td([^>]*data-cell="' . preg_quote($sheetName . ':' . $cell, '/') . '"[^>]*)>.*?<\/td>/s',
                            function($matches) use ($escapedValue) {
                                return '<td' . $matches[1] . '><div style="padding:4px;"><strong>' . $escapedValue . '</strong></div></td>';
                            },
                            $sheetHtml
                        );
                    }
                }
            }

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
                    'printBackground' => true,
                ]
            ]);

            if ($response->successful()) {
                return response($response->body(), 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="document-' . $document->id . '.pdf"'
                ]);
            } else {
                // \Log::error('PDF Service Error: ' . $response->body());
                return response()->json([
                    'error' => 'Failed to generate PDF',
                    'message' => 'PDF service returned error'
                ], 500);
            }
        } catch (\Exception $e) {
            // \Log::error('PDF Service Connection Error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to connect to PDF service',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function exportExcel(Document $document)
    {
        if (!$document->template_document_id) {
            return response()->json(['error' => 'Document has no template'], 400);
        }

        $template = $document->template;
        if (!$template || !$template->excel_file) {
            return response()->json(['error' => 'Template Excel file not found'], 404);
        }

        $excelPath = storage_path('app/public/' . $template->excel_file);
        
        if (!file_exists($excelPath)) {
            return response()->json(['error' => 'Excel file does not exist'], 404);
        }

        // \Log::info('=== EXPORT EXCEL DEBUG ===');
        // \Log::info('Excel Path: ' . $excelPath);
        // \Log::info('Document ID: ' . $document->id);

        $spreadsheet = IOFactory::load($excelPath);
        $formData = $document->form_data ?? [];
        
        // \Log::info('Form Data: ' . json_encode($formData));

        foreach ($formData as $sheetName => $cells) {
            try {
                $worksheet = $spreadsheet->getSheetByName($sheetName);
                
                if (!$worksheet) {
                    // \Log::warning("Sheet '{$sheetName}' not found in Excel file");
                    continue;
                }

                // \Log::info("Processing sheet: {$sheetName}");
                // \Log::info("Total cells to fill: " . count($cells));

                foreach ($cells as $cellCoord => $value) {
                    try {
                        if (is_array($value) && isset($value['type']) && $value['type'] === 'signature') {
                            $approver = \App\Models\User::find($value['approver_id']);
                            $cellValue = $approver ? "✓ {$approver->name}\nSigned: " . date('Y-m-d H:i', strtotime($value['signed_at'])) : '';
                            $worksheet->setCellValue($cellCoord, $cellValue);
                            // \Log::info("Set {$cellCoord}: signature");
                        } elseif (is_array($value) && isset($value['type']) && $value['type'] === 'date') {
                            $worksheet->setCellValue($cellCoord, $value['date']);
                            // \Log::info("Set {$cellCoord}: {$value['date']}");
                        } elseif (is_bool($value)) {
                            $worksheet->setCellValue($cellCoord, $value ? 'TRUE' : 'FALSE');
                            // \Log::info("Set {$cellCoord}: " . ($value ? 'TRUE' : 'FALSE'));
                        } elseif ($value !== '' && $value !== null) {
                            $worksheet->setCellValue($cellCoord, $value);
                            // \Log::info("Set {$cellCoord}: {$value}");
                        } else {
                            // \Log::info("Skip {$cellCoord}: empty value");
                        }
                    } catch (\Exception $e) {
                        // \Log::error("Error setting cell {$cellCoord}: " . $e->getMessage());
                    }
                }
            } catch (\Exception $e) {
                // \Log::error("Error processing sheet {$sheetName}: " . $e->getMessage());
            }
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        $filename = 'document-' . $document->id . '.xlsx';
        $tempFile = storage_path('app/temp/' . $filename);

        if (!file_exists(dirname($tempFile))) {
            mkdir(dirname($tempFile), 0755, true);
        }

        $writer->save($tempFile);
        
        // \Log::info('Excel saved to: ' . $tempFile);

        return response()->download($tempFile, $filename)->deleteFileAfterSend(true);
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