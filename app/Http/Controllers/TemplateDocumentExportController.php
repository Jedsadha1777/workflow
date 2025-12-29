<?php

namespace App\Http\Controllers;

use App\Models\TemplateDocument;
use Illuminate\Support\Facades\Http;

class TemplateDocumentExportController extends Controller
{
    public function previewPdf(TemplateDocument $templateDocument)
    {
        $content = $templateDocument->content;
        if (is_string($content)) {
            $content = json_decode($content, true);
        }

        $sheets = $content['sheets'] ?? [];
        
        // รับ orientation จาก query parameter ถ้ามี ไม่งั้นใช้จาก database
        $orientation = request('orientation') ?? $templateDocument->pdf_orientation ?? 'portrait';
        
        \Log::info('Preview PDF Orientation', [
            'query_param' => request('orientation'),
            'db_value' => $templateDocument->pdf_orientation,
            'final_orientation' => $orientation,
            'all_query' => request()->all()
        ]);

        $html = view('pdf.document-puppeteer', [
            'document' => null,
            'sheets' => $sheets,
            'orientation' => $orientation,
        ])->render();

        $pdfServiceUrl = config('services.pdf.url', 'http://localhost:3000');
        
        try {
            $options = [
                'orientation' => $orientation,
                'printBackground' => true,
            ];
            
            // ถ้าไม่ใช่ fit mode ถึงใส่ format
            if ($orientation !== 'fit') {
                $options['format'] = 'A4';
            }
            
            $response = Http::timeout(60)->post($pdfServiceUrl . '/api/generate-pdf', [
                'html' => $html,
                'options' => $options
            ]);

            if ($response->successful()) {
                return response($response->body(), 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="template-' . $templateDocument->id . '-preview.pdf"'
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
}