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
        
        $orientation = request('orientation') ?? $templateDocument->pdf_orientation ?? 'portrait';


        $html = view('pdf.document-puppeteer', [
            'document' => null,
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
