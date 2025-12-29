<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

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

        $orientation = 'portrait';
        if ($document->template_document_id) {
            $template = $document->template;
            if ($template) {
                $orientation = $template->pdf_orientation ?? 'portrait';
            }
        }

        foreach ($sheets as &$sheet) {
            $sheetHtml = $sheet['html'];
            $sheetName = $sheet['name'];

            if (!empty($formData) && isset($formData[$sheetName])) {
                foreach ($formData[$sheetName] as $cell => $value) {
                    // ข้ามถ้าค่ายังเป็น shortcode component
                    if (is_string($value)) {
                        $trimmedValue = trim($value);
                        if (preg_match('/^\[(signature|input|textarea|date|select|checkbox|radio|number)/', $trimmedValue)) {
                            continue;
                        }
                        if (empty($trimmedValue)) {
                            continue;
                        }
                    }
                    
                    if (is_array($value) && isset($value['type']) && $value['type'] === 'signature') {
                        // ถ้ายังไม่มี approver_id หรือยังไม่ได้ sign → แสดง placeholder
                        if (empty($value['approver_id']) || empty($value['signed_at'])) {
                            $signatureHtml = '<div class="border-2 border-dashed border-gray-300 rounded p-4 text-center text-gray-500">Signature will appear here after approval</div>';
                        } else {
                            $approver = \App\Models\User::find($value['approver_id']);
                            
                            if ($approver && $approver->signature_image) {
                                $signaturePath = storage_path('app/public/' . $approver->signature_image);
                                
                                if (file_exists($signaturePath)) {
                                    $imageData = base64_encode(file_get_contents($signaturePath));
                                    $imageMimeType = mime_content_type($signaturePath);
                                    $base64Image = 'data:' . $imageMimeType . ';base64,' . $imageData;
                                    
                                    $signatureHtml = '<div style="text-align:center;">' .
                                        '<img src="' . $base64Image . '" style="max-width:150px;max-height:60px;display:block;margin:0 auto;" alt="Signature">' .
                                        '</div>';
                                } else {
                                    $signatureHtml = '<div style="border:2px solid #10b981;padding:10px;text-align:center;background:#f0fdf4;border-radius:6px;">' .
                                        '<div style="font-weight:bold;color:#065f46;">✓ ' . htmlspecialchars($approver->name) . '</div>' .
                                        '</div>';
                                }
                            } else {
                                $signatureHtml = '<div style="border:2px solid #10b981;padding:10px;text-align:center;background:#f0fdf4;border-radius:6px;">' .
                                    '<div style="font-weight:bold;color:#065f46;">✓ ' . htmlspecialchars($approver ? $approver->name : 'Unknown') . '</div>' .
                                    '</div>';
                            }
                        }
                        
                        $sheetHtml = preg_replace(
                            '/<td([^>]*data-cell="' . preg_quote($sheetName . ':' . $cell, '/') . '"[^>]*)>.*?<\/td>/s',
                            '<td$1>' . $signatureHtml . '</td>',
                            $sheetHtml
                        );
                    } elseif (is_array($value) && isset($value['type']) && $value['type'] === 'date') {
                        // ข้ามถ้ายังไม่ได้เลือกวันที่
                        if (empty($value['date'])) {
                            continue;
                        }
                        
                        // แปลงเป็น dd/mm/yyyy
                        $date = new \DateTime($value['date']);
                        $formattedDate = $date->format('d/m/Y');
                        
                        $dateHtml = '<div style="padding:4px;">' . htmlspecialchars($formattedDate) . '</div>';
                        
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
                                return '<td' . $matches[1] . '><div style="padding:4px;">' . $escapedValue . '</div></td>';
                            },
                            $sheetHtml
                        );
                    }
                }
            }
            
            // แทนที่ #VALUE!, #DIV/0!, #REF!, #N/A, #NAME? ด้วยค่าว่าง
            $sheetHtml = preg_replace('/#VALUE!|#DIV\/0!|#REF!|#N\/A|#NAME\?/i', '', $sheetHtml);
            
            // แทนที่ shortcode components ด้วย div เปล่าเพื่อรักษา structure
            $sheetHtml = preg_replace('/\[(?:signature|input|textarea|date|select|checkbox|radio|number|text)(?:\s+[^\]]+)?\]/i', '<div style="padding:4px;">&nbsp;</div>', $sheetHtml);

            $sheet['html'] = $sheetHtml;
        }

        $html = view('pdf.document-puppeteer', [
            'document' => $document,
            'sheets' => $sheets,
            'orientation' => $orientation,
        ])->render();

        \Log::info('PDF Export Debug', [
            'document_id' => $document->id,
            'template_id' => $document->template_document_id,
            'orientation' => $orientation,
            'sheets_count' => count($sheets),
        ]);
        
        // Save HTML for debugging
        file_put_contents(storage_path('logs/last-pdf-export.html'), $html);

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
                return response()->json([
                    'error' => 'Failed to generate PDF',
                    'message' => 'PDF service returned error'
                ], 500);
            }
        } catch (\Exception $e) {
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

        $spreadsheet = IOFactory::load($excelPath);
        $formData = $document->form_data ?? [];

        foreach ($formData as $sheetName => $cells) {
            try {
                $worksheet = $spreadsheet->getSheetByName($sheetName);
                
                if (!$worksheet) {
                    continue;
                }

                foreach ($cells as $cellCoord => $value) {
                    try {

                        // ถ้า cell มีสูตร skip 
                        $cell = $worksheet->getCell($cellCoord);
                        if ($cell->isFormula()) {
                            continue;
                        }


                        if (is_array($value) && isset($value['type']) && $value['type'] === 'signature') {
                            $approver = \App\Models\User::find($value['approver_id']);
                            
                            if ($approver && $approver->signature_image) {
                                $signaturePath = storage_path('app/public/' . $approver->signature_image);
                                
                                \Log::info("Excel Signature Debug", [
                                    'approver' => $approver->name,
                                    'signature_image' => $approver->signature_image,
                                    'full_path' => $signaturePath,
                                    'file_exists' => file_exists($signaturePath),
                                    'cell' => $cellCoord
                                ]);
                                
                                if (file_exists($signaturePath)) {
                                    try {
                                        $drawing = new Drawing();
                                        $drawing->setName('Signature');
                                        $drawing->setDescription($approver->name . ' - Signed: ' . date('Y-m-d H:i', strtotime($value['signed_at'])));
                                        $drawing->setPath($signaturePath);
                                        $drawing->setCoordinates($cellCoord);
                                        $drawing->setHeight(60);
                                        $drawing->setOffsetX(5);
                                        $drawing->setOffsetY(5);
                                        $drawing->setWorksheet($worksheet);
                                        
                                        $rowNumber = $worksheet->getCell($cellCoord)->getRow();
                                        $worksheet->getRowDimension($rowNumber)->setRowHeight(60);
                                        
                                        \Log::info("Excel Signature Success", [
                                            'cell' => $cellCoord,
                                            'approver' => $approver->name
                                        ]);
                                    } catch (\Exception $e) {
                                        \Log::error("Excel Drawing Error", [
                                            'error' => $e->getMessage(),
                                            'cell' => $cellCoord
                                        ]);
                                        $cellValue = "✓ {$approver->name}\nSigned: " . date('Y-m-d H:i', strtotime($value['signed_at']));
                                        $worksheet->setCellValue($cellCoord, $cellValue);
                                    }
                                } else {
                                    \Log::warning("Excel Signature File Not Found", [
                                        'path' => $signaturePath,
                                        'cell' => $cellCoord
                                    ]);
                                    $cellValue = "✓ {$approver->name}\nSigned: " . date('Y-m-d H:i', strtotime($value['signed_at']));
                                    $worksheet->setCellValue($cellCoord, $cellValue);
                                }
                            } else {
                                $cellValue = $approver ? "✓ {$approver->name}\nSigned: " . date('Y-m-d H:i', strtotime($value['signed_at'])) : '';
                                $worksheet->setCellValue($cellCoord, $cellValue);
                            }
                        } elseif (is_array($value) && isset($value['type']) && $value['type'] === 'date') {
                            $worksheet->setCellValue($cellCoord, $value['date']);
                        } elseif (is_bool($value)) {
                            $worksheet->setCellValue($cellCoord, $value ? 'TRUE' : 'FALSE');
                        } elseif ($value !== '' && $value !== null) {
                            $worksheet->setCellValue($cellCoord, $value);
                        }
                    } catch (\Exception $e) {
                        \Log::error("Excel Cell Error", [
                            'cell' => $cellCoord,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            } catch (\Exception $e) {
                // Silent error
            }
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        $filename = 'document-' . $document->id . '.xlsx';
        $tempFile = storage_path('app/temp/' . $filename);

        if (!file_exists(dirname($tempFile))) {
            mkdir(dirname($tempFile), 0755, true);
        }

        $writer->save($tempFile);

        return response()->download($tempFile, $filename)->deleteFileAfterSend(true);
    }
}